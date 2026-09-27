<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Concerns;

use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Support\TestMedia;
use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Support\TestModel;

trait CreatesMedia
{
    protected function makeImageFile(string $extension = 'jpg', int $width = 64, int $height = 48): string
    {
        $image = imagecreatetruecolor($width, $height);

        for ($x = 0; $x < $width; $x++) {
            imagefilledrectangle($image, $x, 0, $x, $height - 1, imagecolorallocate($image, (int) ($x * 255 / $width), 80, 160));
        }

        imagefilledellipse($image, (int) ($width / 3), (int) ($height / 2), (int) ($width / 4), (int) ($height / 3), imagecolorallocate($image, 250, 250, 250));

        $path = sys_get_temp_dir().'/'.uniqid('media-hasher-fixture-').'.'.$extension;

        match ($extension) {
            'png' => imagepng($image, $path),
            'webp' => imagewebp($image, $path),
            default => imagejpeg($image, $path, 90),
        };

        imagedestroy($image);

        return $path;
    }

    protected function makeTextFile(string $contents = 'plain text'): string
    {
        $path = sys_get_temp_dir().'/'.uniqid('media-hasher-fixture-').'.txt';
        file_put_contents($path, $contents);

        return $path;
    }

    protected function addMedia(string $path, string $collection = 'default'): TestMedia
    {
        /** @var TestMedia */
        return TestModel::query()->create()
            ->addMedia($path)
            ->preservingOriginal()
            ->toMediaCollection($collection);
    }
}
