<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Support;

class RecordingObserver
{
    /** @var list<string> */
    public static array $calls = [];

    public static bool $cancelHashing = false;

    public function hashing(TestMedia $media): ?bool
    {
        self::$calls[] = "hashing:{$media->id}";

        return self::$cancelHashing ? false : null;
    }

    public function hashed(TestMedia $media): void
    {
        self::$calls[] = "hashed:{$media->id}";
    }
}
