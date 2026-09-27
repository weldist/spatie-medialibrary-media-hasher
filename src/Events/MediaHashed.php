<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaHashed
{
    use Dispatchable;

    /**
     * @param  array<string, string>  $hashes  The hashes computed in this run, keyed by hasher name.
     */
    public function __construct(
        public readonly Media $media,
        public readonly array $hashes,
    ) {}
}
