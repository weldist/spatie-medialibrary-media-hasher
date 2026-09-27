<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Hashers;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Weldist\Spatie\MediaLibrary\MediaHasher\Contracts\Hasher;

class Sha256Hasher implements Hasher
{
    public function supports(Media $media): bool
    {
        return true;
    }

    public function hash(string $path): string
    {
        return hash_file('sha256', $path);
    }
}
