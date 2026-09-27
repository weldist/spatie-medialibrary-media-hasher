<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Console;

use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Weldist\Spatie\MediaLibrary\MediaHasher\Console\Concerns\FiltersMedia;
use Weldist\Spatie\MediaLibrary\MediaHasher\MediaHasher;

class HashCleanCommand extends Command
{
    use FiltersMedia;

    protected $signature = 'media-library:hash:clean
        {--dry-run : Show what would be removed without modifying anything}'.self::MEDIA_FILTERS;

    protected $description = 'Remove the stored hashes of hashers that are no longer configured.';

    public function handle(MediaHasher $mediaHasher): int
    {
        $configured = array_keys(config('media-hasher.hashers', []));
        $property = config('media-hasher.property');
        $dryRun = (bool) $this->option('dry-run');
        $removed = [];

        $this->eachMedia($this->mediaWithHashesQuery(), function (Media $media) use ($mediaHasher, $configured, $property, $dryRun, &$removed): void {
            $unconfigured = array_values(array_diff(array_keys((array) $media->getCustomProperty($property, [])), $configured));

            if ($unconfigured === []) {
                return;
            }

            foreach ($dryRun ? $unconfigured : $mediaHasher->forget($media, $unconfigured) as $name) {
                $removed[$name] = ($removed[$name] ?? 0) + 1;
            }
        });

        if ($removed === []) {
            $this->info('No hashes of unconfigured hashers found.');

            return self::SUCCESS;
        }

        $this->table(['Hash', $dryRun ? 'Would remove from media' : 'Removed from media'], array_map(null, array_keys($removed), $removed));

        return self::SUCCESS;
    }
}
