<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Console;

use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;
use Weldist\Spatie\MediaLibrary\MediaHasher\Console\Concerns\FiltersMedia;
use Weldist\Spatie\MediaLibrary\MediaHasher\Jobs\HashMediaJob;
use Weldist\Spatie\MediaLibrary\MediaHasher\MediaHasher;

class HashGenerateCommand extends Command
{
    use FiltersMedia;

    protected $signature = 'media-library:hash:generate
        {--hasher=* : Limit to one or more configured hasher names}
        {--force : Recompute hashes that are already stored}
        {--verify : Recompute every hash and store only the missing or changed ones}
        {--queue : Dispatch a queued job per row instead of hashing synchronously}'.self::MEDIA_FILTERS;

    protected $description = 'Compute and store the configured hashes of existing media files.';

    public function handle(MediaHasher $mediaHasher): int
    {
        $hashers = $this->option('hasher');
        $force = (bool) $this->option('force');
        $verify = (bool) $this->option('verify');
        $useQueue = (bool) $this->option('queue');

        if ($this->failOnUnknownHashers($hashers)) {
            return self::FAILURE;
        }

        if ($force && $verify) {
            $this->error('The --force and --verify options cannot be combined.');

            return self::FAILURE;
        }

        $stats = ['hashed' => 0, 'skipped' => 0, 'queued' => 0, 'failed' => 0];

        $total = $this->eachMedia($this->mediaQuery(), function (Media $media) use ($mediaHasher, $hashers, $force, $verify, $useQueue, &$stats): void {
            try {
                if ($useQueue) {
                    dispatch(new HashMediaJob($media->getKey(), $hashers, $force, $verify));
                    $stats['queued']++;

                    return;
                }

                $stored = $verify ? $mediaHasher->verify($media, $hashers) : $mediaHasher->hash($media, $hashers, $force);
                $stored === [] ? $stats['skipped']++ : $stats['hashed']++;
            } catch (Throwable $e) {
                $stats['failed']++;
                $this->newLine();
                $this->error("Media #{$media->getKey()}: {$e->getMessage()}");
            }
        });

        if ($total === 0) {
            $this->info('No matching media rows found.');

            return self::SUCCESS;
        }

        $this->table(['Status', 'Count'], $useQueue
            ? [['Queued', $stats['queued']], ['Failed to dispatch', $stats['failed']]]
            : [[$verify ? 'Updated' : 'Hashed', $stats['hashed']], ['Skipped', $stats['skipped']], ['Failed', $stats['failed']]]);

        return $stats['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
