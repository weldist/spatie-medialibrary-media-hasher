<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Hashers;

use Imagick;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Weldist\Spatie\MediaLibrary\MediaHasher\Contracts\Hasher;

/**
 * 64-bit difference hash (dHash) as 16 hex characters. Visually identical images
 * produce the same or a nearby hash regardless of format, size or re-encoding.
 */
class PerceptualHasher implements Hasher
{
    private const array UNSUPPORTED_MIME_TYPES = ['image/svg+xml'];

    public function supports(Media $media): bool
    {
        return str_starts_with((string) $media->mime_type, 'image/')
            && ! in_array($media->mime_type, self::UNSUPPORTED_MIME_TYPES, true);
    }

    public function hash(string $path): string
    {
        return self::fromImage(self::flatten(new Imagick($path)));
    }

    public static function fromImage(Imagick $image): string
    {
        $image = clone $image;
        $image->transformImageColorspace(Imagick::COLORSPACE_GRAY);
        $image->resizeImage(9, 8, Imagick::FILTER_TRIANGLE, 1);

        $pixels = $image->exportImagePixels(0, 0, 9, 8, 'I', Imagick::PIXEL_CHAR);

        $bits = '';

        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $bits .= $pixels[$y * 9 + $x] > $pixels[$y * 9 + $x + 1] ? '1' : '0';
            }
        }

        return implode('', array_map(
            fn (string $nibble): string => dechex(bindec($nibble)),
            str_split($bits, 4),
        ));
    }

    /**
     * Number of differing bits between two hashes; 0 means identical.
     */
    public static function distance(string $first, string $second): int
    {
        $distance = 0;

        for ($i = 0; $i < 16; $i++) {
            $distance += substr_count(decbin(hexdec($first[$i]) ^ hexdec($second[$i])), '1');
        }

        return $distance;
    }

    private static function flatten(Imagick $image): Imagick
    {
        $image->setIteratorIndex(0);

        $frame = $image->getImage();
        $frame->setImageBackgroundColor('white');

        return $frame->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
    }
}
