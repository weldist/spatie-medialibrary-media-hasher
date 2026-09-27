<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Exceptions;

use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaFileNotFound extends RuntimeException
{
    public static function for(Media $media): self
    {
        return new self("The file of media #{$media->getKey()} was not found at [{$media->getPathRelativeToRoot()}] on disk [{$media->disk}].");
    }
}
