<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Query\Expression;
use InvalidArgumentException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Weldist\Spatie\MediaLibrary\MediaHasher\Contracts\Hasher;
use Weldist\Spatie\MediaLibrary\MediaHasher\Events\MediaHashed;
use Weldist\Spatie\MediaLibrary\MediaHasher\Events\MediaHashesRemoved;
use Weldist\Spatie\MediaLibrary\MediaHasher\Events\MediaHashing;

class MediaHasher
{
    public function __construct(
        private readonly Container $container,
        private readonly Dispatcher $events,
    ) {}

    /**
     * Computes the missing hashes of the media file and stores them in its custom properties.
     *
     * @param  list<string>  $only  Hasher names to run; empty runs every configured hasher.
     * @param  bool  $force  Recompute hashes that are already stored.
     * @return array<string, string> The hashes stored in this run, keyed by hasher name.
     */
    public function hash(Media $media, array $only = [], bool $force = false): array
    {
        $hashers = array_filter(
            $this->supportedHashers($media, $only),
            fn (string $name): bool => $force || ! $media->hasCustomProperty($this->path($name)),
            ARRAY_FILTER_USE_KEY,
        );

        return $this->run($media, $hashers, onlyChanged: false);
    }

    /**
     * Recomputes every supported hash and stores only the ones that are missing or no longer match the file.
     *
     * @param  list<string>  $only  Hasher names to run; empty runs every configured hasher.
     * @return array<string, string> The hashes stored in this run, keyed by hasher name.
     */
    public function verify(Media $media, array $only = []): array
    {
        return $this->run($media, $this->supportedHashers($media, $only), onlyChanged: true);
    }

    /**
     * @param  list<string>  $only  Hasher names to remove; empty removes every stored hash.
     * @return list<string> The names of the removed hashes.
     */
    public function forget(Media $media, array $only = []): array
    {
        $removed = [];

        $this->write($media, function (array $hashes) use ($only, &$removed): array {
            $removed = array_keys($only === [] ? $hashes : array_intersect_key($hashes, array_flip($only)));

            return array_diff_key($hashes, array_flip($removed));
        });

        if ($removed !== []) {
            $this->fireModelEvent($media, 'hashesRemoved');
            $this->events->dispatch(new MediaHashesRemoved($media, $removed));
        }

        return $removed;
    }

    /**
     * @return array<string, Hasher>
     */
    public function hashers(): array
    {
        $hashers = [];

        foreach (config('media-hasher.hashers', []) as $name => $class) {
            $hasher = $this->container->make($class);

            if (! $hasher instanceof Hasher) {
                throw new InvalidArgumentException("Hasher [{$name}] must implement ".Hasher::class.'.');
            }

            $hashers[$name] = $hasher;
        }

        return $hashers;
    }

    /**
     * @param  list<string>  $only
     * @return array<string, Hasher>
     */
    private function supportedHashers(Media $media, array $only): array
    {
        return array_filter(
            $this->hashers(),
            fn (Hasher $hasher, string $name): bool => ($only === [] || in_array($name, $only, true)) && $hasher->supports($media),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @param  array<string, Hasher>  $hashers
     * @return array<string, string>
     */
    private function run(Media $media, array $hashers, bool $onlyChanged): array
    {
        if ($hashers === [] || ! $this->fireHashingEvents($media, array_keys($hashers))) {
            return [];
        }

        $hashes = $this->compute($media, $hashers);

        if ($onlyChanged) {
            $hashes = array_filter(
                $hashes,
                fn (string $hash, string $name): bool => $media->getCustomProperty($this->path($name)) !== $hash,
                ARRAY_FILTER_USE_BOTH,
            );
        }

        if ($hashes === []) {
            return [];
        }

        $this->write($media, fn (array $stored): array => array_merge($stored, $hashes));

        $this->fireModelEvent($media, 'hashed');
        $this->events->dispatch(new MediaHashed($media, $hashes));

        return $hashes;
    }

    /**
     * @param  list<string>  $hasherNames
     */
    private function fireHashingEvents(Media $media, array $hasherNames): bool
    {
        if ($this->fireModelEvent($media, 'hashing', halt: true) === false) {
            return false;
        }

        return $this->events->until(new MediaHashing($media, $hasherNames)) !== false;
    }

    private function fireModelEvent(Media $media, string $event, bool $halt = false): mixed
    {
        $name = "eloquent.{$event}: ".$media::class;

        return $halt ? $this->events->until($name, $media) : $this->events->dispatch($name, $media);
    }

    /**
     * @param  array<string, Hasher>  $hashers
     * @return array<string, string>
     */
    private function compute(Media $media, array $hashers): array
    {
        $path = $this->copyToTemporaryFile($media);

        try {
            return array_map(fn (Hasher $hasher): string => $hasher->hash($path), $hashers);
        } finally {
            @unlink($path);
        }
    }

    private function copyToTemporaryFile(Media $media): string
    {
        $path = tempnam(sys_get_temp_dir(), 'media-hasher-');
        $target = fopen($path, 'wb');
        $source = $media->stream();

        try {
            stream_copy_to_stream($source, $target);
        } finally {
            fclose($target);

            if (is_resource($source)) {
                fclose($source);
            }
        }

        return $path;
    }

    /**
     * Only the hash property is written, so custom properties changed by other
     * writers in the meantime are kept.
     *
     * @param  Closure(array<string, string>): array<string, string>  $mutate
     */
    private function write(Media $media, Closure $mutate): void
    {
        $property = config('media-hasher.property');

        $hashes = $media->getConnection()->transaction(function () use ($media, $property, $mutate): array {
            $query = $media->newQuery()->whereKey($media->getKey());
            $fresh = (clone $query)->lockForUpdate()->firstOrFail();

            $current = (array) $fresh->getCustomProperty($property, []);
            $hashes = $mutate($current);

            if ($hashes === $current) {
                return $hashes;
            }

            $query->toBase()->update(['custom_properties' => $this->jsonKeyExpression($media, $property, $hashes)]);

            return $hashes;
        });

        $hashes === [] ? $media->forgetCustomProperty($property) : $media->setCustomProperty($property, $hashes);
        $media->syncOriginalAttribute('custom_properties');
    }

    /**
     * Replaces the value of one top-level key of the custom properties column,
     * or removes the key when the value is empty.
     *
     * @param  array<string, string>  $value
     */
    private function jsonKeyExpression(Media $media, string $key, array $value): Expression
    {
        $connection = $media->getConnection();
        $pdo = $connection->getPdo();
        $column = $connection->getQueryGrammar()->wrap('custom_properties');
        $path = $pdo->quote('$."'.$key.'"');
        $json = $pdo->quote(json_encode($value, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT));
        $driver = $connection->getDriverName();

        if ($value === []) {
            return new Expression(match ($driver) {
                'pgsql' => "({$column}::jsonb - {$pdo->quote($key)})::json",
                'sqlsrv' => "json_modify({$column}, {$path}, null)",
                default => "json_remove({$column}, {$path})",
            });
        }

        // media-library stores empty custom properties as a JSON array, which a key cannot be set on.
        return new Expression(match ($driver) {
            'pgsql' => "jsonb_set(case when json_typeof({$column}) = 'object' then {$column}::jsonb else '{}'::jsonb end, {$pdo->quote('{'.$key.'}')}, {$json}::jsonb)::json",
            'sqlsrv' => "json_modify(case when left(ltrim({$column}), 1) = '{' then {$column} else '{}' end, {$path}, json_query({$json}))",
            'sqlite' => "json_set(case when json_type({$column}) = 'object' then {$column} else json('{}') end, {$path}, json({$json}))",
            'mariadb' => "json_set(if(json_type({$column}) = 'OBJECT', {$column}, json_object()), {$path}, json_extract({$json}, '$'))",
            default => "json_set(if(json_type({$column}) = 'OBJECT', {$column}, json_object()), {$path}, cast({$json} as json))",
        });
    }

    private function path(string $hasher): string
    {
        return config('media-hasher.property').'.'.$hasher;
    }
}
