<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Weldist\Spatie\MediaLibrary\MediaHasher\Concerns\InteractsWithHashes;

class TestMedia extends Media
{
    use InteractsWithHashes;

    protected $table = 'media';
}
