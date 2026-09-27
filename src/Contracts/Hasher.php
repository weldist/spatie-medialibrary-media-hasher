<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Contracts;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

interface Hasher
{
    public function supports(Media $media): bool;

    /**
     * @param  string  $path  Local path of a temporary copy of the media file.
     */
    public function hash(string $path): string;
}
