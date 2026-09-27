<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaHashesRemoved
{
    use Dispatchable;

    /**
     * @param  list<string>  $hashers  The names of the removed hashes.
     */
    public function __construct(
        public readonly Media $media,
        public readonly array $hashers,
    ) {}
}
