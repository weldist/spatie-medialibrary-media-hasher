<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Weldist\Spatie\MediaLibrary\MediaHasher\MediaHasher;

class HashMediaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * @param  list<string>  $hashers
     */
    public function __construct(
        public readonly int $mediaId,
        public readonly array $hashers = [],
        public readonly bool $force = false,
        public readonly bool $verify = false,
    ) {
        $this->onConnection(config('media-hasher.queue_connection'));
        $this->onQueue(config('media-hasher.queue_name'));
    }

    public function handle(MediaHasher $hasher): void
    {
        $mediaModel = config('media-library.media_model', Media::class);
        $media = $mediaModel::find($this->mediaId);

        if ($media === null) {
            return;
        }

        $this->verify
            ? $hasher->verify($media, $this->hashers)
            : $hasher->hash($media, $this->hashers, $this->force);
    }
}
