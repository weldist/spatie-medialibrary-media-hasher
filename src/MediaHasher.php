<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use InvalidArgumentException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Weldist\Spatie\MediaLibrary\MediaHasher\Contracts\Hasher;
use Weldist\Spatie\MediaLibrary\MediaHasher\Events\MediaHashed;
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
     * @return array<string, string> The hashes computed in this run, keyed by hasher name.
     */
    public function hash(Media $media, array $only = [], bool $force = false): array
    {
        $hashers = $this->pendingHashers($media, $only, $force);

        if ($hashers === [] || ! $this->fireHashingEvents($media, array_keys($hashers))) {
            return [];
        }

        $hashes = $this->compute($media, $hashers);

        $this->store($media, $hashes);

        $this->fireModelEvent($media, 'hashed');
        $this->events->dispatch(new MediaHashed($media, $hashes));

        return $hashes;
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
    private function pendingHashers(Media $media, array $only, bool $force): array
    {
        $property = config('media-hasher.property');

        return array_filter(
            $this->hashers(),
            fn (Hasher $hasher, string $name): bool => ($only === [] || in_array($name, $only, true))
                && ($force || ! $media->hasCustomProperty("{$property}.{$name}"))
                && $hasher->supports($media),
            ARRAY_FILTER_USE_BOTH,
        );
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
     * @param  array<string, string>  $hashes
     */
    private function store(Media $media, array $hashes): void
    {
        $property = config('media-hasher.property');

        $stored = $media->getConnection()->transaction(function () use ($media, $property, $hashes): array {
            $fresh = $media->newQuery()->whereKey($media->getKey())->lockForUpdate()->firstOrFail();

            $stored = array_merge((array) $fresh->getCustomProperty($property, []), $hashes);

            $media->newQuery()->whereKey($media->getKey())->toBase()->update(["custom_properties->{$property}" => $stored]);

            return $stored;
        });

        $media->setCustomProperty($property, $stored);
        $media->syncOriginalAttribute('custom_properties');
    }
}
