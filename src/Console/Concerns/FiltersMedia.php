<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Console\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait FiltersMedia
{
    protected const string MEDIA_FILTERS = '
        {--collection=* : Limit to one or more media collection names}
        {--model=* : Limit to one or more model FQCNs}
        {--id-from= : Only include media with id >= this value}
        {--id-to= : Only include media with id <= this value}
        {--chunk=100 : Number of Media rows to process per database chunk}';

    protected function mediaQuery(): Builder
    {
        $mediaModel = config('media-library.media_model', Media::class);

        return $mediaModel::query()
            ->when($this->option('collection'), fn (Builder $query, array $collections) => $query->whereIn('collection_name', $collections))
            ->when($this->option('model'), fn (Builder $query, array $models) => $query->whereIn('model_type', $models))
            ->when($this->option('id-from'), fn (Builder $query, string $id) => $query->where('id', '>=', (int) $id))
            ->when($this->option('id-to'), fn (Builder $query, string $id) => $query->where('id', '<=', (int) $id));
    }

    protected function mediaWithHashesQuery(): Builder
    {
        return $this->mediaQuery()->whereNotNull('custom_properties->'.config('media-hasher.property'));
    }

    /**
     * @param  Closure(Media): void  $callback
     */
    protected function eachMedia(Builder $query, Closure $callback): int
    {
        $total = (clone $query)->count();

        if ($total === 0) {
            return 0;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById((int) $this->option('chunk'), function ($chunk) use ($callback, $bar): void {
            foreach ($chunk as $media) {
                $callback($media);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        return $total;
    }

    /**
     * @param  list<string>  $names
     */
    protected function failOnUnknownHashers(array $names): bool
    {
        $unknown = array_diff($names, array_keys(config('media-hasher.hashers', [])));

        if ($unknown === []) {
            return false;
        }

        $this->error('Unknown hasher(s): '.implode(', ', $unknown));

        return true;
    }
}
