<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;
use Weldist\Spatie\MediaLibrary\MediaHasher\Jobs\HashMediaJob;
use Weldist\Spatie\MediaLibrary\MediaHasher\MediaHasher;

class HashMediaCommand extends Command
{
    protected $signature = 'media-library:hash
        {--hasher=* : Limit to one or more configured hasher names}
        {--collection=* : Limit to one or more media collection names}
        {--model=* : Limit to one or more model FQCNs}
        {--id-from= : Only include media with id >= this value}
        {--id-to= : Only include media with id <= this value}
        {--chunk=100 : Number of Media rows to process per database chunk}
        {--force : Recompute hashes that are already stored}
        {--queue : Dispatch a queued job per row instead of hashing synchronously}';

    protected $description = 'Compute and store the configured hashes of existing media files.';

    public function handle(MediaHasher $hasher): int
    {
        $hasherNames = $this->option('hasher');
        $unknown = array_diff($hasherNames, array_keys(config('media-hasher.hashers', [])));

        if ($unknown !== []) {
            $this->error('Unknown hasher(s): '.implode(', ', $unknown));

            return self::FAILURE;
        }

        $query = $this->buildQuery(config('media-library.media_model', Media::class));
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No matching media rows found.');

            return self::SUCCESS;
        }

        $useQueue = (bool) $this->option('queue');
        $force = (bool) $this->option('force');
        $stats = ['hashed' => 0, 'skipped' => 0, 'queued' => 0, 'failed' => 0];

        $this->info(($useQueue ? 'Queueing' : 'Processing')." {$total} media row(s)...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById((int) $this->option('chunk'), function ($chunk) use (&$stats, $bar, $hasher, $hasherNames, $force, $useQueue): void {
            foreach ($chunk as $media) {
                try {
                    if ($useQueue) {
                        dispatch(new HashMediaJob($media->getKey(), $hasherNames, $force));
                        $stats['queued']++;
                    } else {
                        $hasher->hash($media, $hasherNames, $force) === [] ? $stats['skipped']++ : $stats['hashed']++;
                    }
                } catch (Throwable $e) {
                    $stats['failed']++;
                    $this->newLine();
                    $this->error("Media #{$media->getKey()}: {$e->getMessage()}");
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->table(['Status', 'Count'], $useQueue
            ? [['Queued', $stats['queued']], ['Failed to dispatch', $stats['failed']]]
            : [['Hashed', $stats['hashed']], ['Skipped', $stats['skipped']], ['Failed', $stats['failed']]]);

        return $stats['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  class-string<Media>  $mediaModel
     */
    private function buildQuery(string $mediaModel): Builder
    {
        return $mediaModel::query()
            ->when($this->option('collection'), fn (Builder $query, array $collections) => $query->whereIn('collection_name', $collections))
            ->when($this->option('model'), fn (Builder $query, array $models) => $query->whereIn('model_type', $models))
            ->when($this->option('id-from'), fn (Builder $query, string $id) => $query->where('id', '>=', (int) $id))
            ->when($this->option('id-to'), fn (Builder $query, string $id) => $query->where('id', '<=', (int) $id));
    }
}
