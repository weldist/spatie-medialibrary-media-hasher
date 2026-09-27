<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Fired before the file is hashed. A listener returning false cancels hashing.
 */
class MediaHashing
{
    use Dispatchable;

    /**
     * @param  list<string>  $hashers
     */
    public function __construct(
        public readonly Media $media,
        public readonly array $hashers,
    ) {}
}
