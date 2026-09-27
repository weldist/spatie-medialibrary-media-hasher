<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Listeners;

use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Weldist\Spatie\MediaLibrary\MediaHasher\Jobs\HashMediaJob;

class QueueAddedMediaHashing
{
    public function handle(MediaHasBeenAddedEvent $event): void
    {
        $collections = config('media-hasher.collections', []);

        if (! in_array('*', $collections, true) && ! in_array($event->media->collection_name, $collections, true)) {
            return;
        }

        dispatch(new HashMediaJob($event->media->getKey()))->afterCommit();
    }
}
