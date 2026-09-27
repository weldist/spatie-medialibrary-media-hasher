<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Weldist\Spatie\MediaLibrary\MediaHasher\Exceptions\MediaFileNotFound;
use Weldist\Spatie\MediaLibrary\MediaHasher\MediaHasher;

class HashMediaJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public ?int $tries;

    /** @var int|list<int>|null */
    public int|array|null $backoff;

    public ?int $timeout;

    /**
     * @param  list<string>  $hashers
     */
    public function __construct(
        public readonly int $mediaId,
        public readonly array $hashers = [],
        public readonly bool $force = false,
        public readonly bool $verify = false,
    ) {
        $this->onConnection(config('media-hasher.queue.connection'));
        $this->onQueue(config('media-hasher.queue.name'));

        $this->tries = config('media-hasher.queue.tries');
        $this->backoff = config('media-hasher.queue.backoff');
        $this->timeout = config('media-hasher.queue.timeout');
    }

    public function uniqueId(): string
    {
        $hashers = $this->hashers;
        sort($hashers);

        return implode(':', [$this->mediaId, implode(',', $hashers), (int) $this->force, (int) $this->verify]);
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return [$this->mediaModel().':'.$this->mediaId];
    }

    public function handle(MediaHasher $hasher): void
    {
        $media = $this->mediaModel()::find($this->mediaId);

        if ($media === null) {
            return;
        }

        try {
            $this->verify
                ? $hasher->verify($media, $this->hashers)
                : $hasher->hash($media, $this->hashers, $this->force);
        } catch (MediaFileNotFound $e) {
            report($e);
        }
    }

    /**
     * @return class-string<Media>
     */
    private function mediaModel(): string
    {
        return config('media-library.media_model', Media::class);
    }
}
