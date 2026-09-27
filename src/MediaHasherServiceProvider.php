<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher;

use Illuminate\Support\ServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Weldist\Spatie\MediaLibrary\MediaHasher\Console\HashMediaCommand;
use Weldist\Spatie\MediaLibrary\MediaHasher\Listeners\QueueAddedMediaHashing;

class MediaHasherServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/media-hasher.php', 'media-hasher');

        $this->app->singleton(MediaHasher::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/media-hasher.php' => config_path('media-hasher.php'),
            ], 'media-hasher-config');

            $this->commands([HashMediaCommand::class]);
        }

        if (config('media-hasher.hash_on_add', true)) {
            $this->app['events']->listen(MediaHasBeenAddedEvent::class, QueueAddedMediaHashing::class);
        }
    }
}
