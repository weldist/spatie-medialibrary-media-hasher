<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Console;

use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Weldist\Spatie\MediaLibrary\MediaHasher\Console\Concerns\FiltersMedia;
use Weldist\Spatie\MediaLibrary\MediaHasher\MediaHasher;

class HashClearCommand extends Command
{
    use FiltersMedia;

    protected $signature = 'media-library:hash:clear
        {--hasher=* : Only remove the hashes with these names, configured or not}
        {--force : Skip the confirmation prompt}'.self::MEDIA_FILTERS;

    protected $description = 'Remove stored hashes, whether their hasher is configured or not.';

    public function handle(MediaHasher $mediaHasher): int
    {
        $hashers = $this->option('hasher');
        $target = $hashers === [] ? 'all stored hashes' : 'the '.implode(', ', $hashers).' hash(es)';

        if (! $this->option('force') && ! $this->confirm("This removes {$target} of the matching media. Continue?")) {
            return self::FAILURE;
        }

        $cleared = 0;

        $this->eachMedia($this->mediaWithHashesQuery(), function (Media $media) use ($mediaHasher, $hashers, &$cleared): void {
            if ($mediaHasher->forget($media, $hashers) !== []) {
                $cleared++;
            }
        });

        $this->info("Removed {$target} from {$cleared} media row(s).");

        return self::SUCCESS;
    }
}
