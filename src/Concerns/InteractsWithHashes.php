<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Registers the "hashing", "hashed" and "hashesRemoved" model events so observers
 * can define methods with those names, and adds hash accessors to the media model.
 */
trait InteractsWithHashes
{
    public function initializeInteractsWithHashes(): void
    {
        $this->addObservableEvents(['hashing', 'hashed', 'hashesRemoved']);
    }

    public static function hashing(Closure|string|array $callback): void
    {
        static::registerModelEvent('hashing', $callback);
    }

    public static function hashed(Closure|string|array $callback): void
    {
        static::registerModelEvent('hashed', $callback);
    }

    public static function hashesRemoved(Closure|string|array $callback): void
    {
        static::registerModelEvent('hashesRemoved', $callback);
    }

    public function getHash(string $hasher): ?string
    {
        return $this->getCustomProperty(config('media-hasher.property').'.'.$hasher);
    }

    /**
     * @return array<string, string>
     */
    public function getHashes(): array
    {
        return (array) $this->getCustomProperty(config('media-hasher.property'), []);
    }

    public function hasHash(string $hasher): bool
    {
        return $this->getHash($hasher) !== null;
    }

    public function scopeWhereHash(Builder $query, string $hasher, string $hash): Builder
    {
        return $query->where('custom_properties->'.config('media-hasher.property').'->'.$hasher, $hash);
    }
}
