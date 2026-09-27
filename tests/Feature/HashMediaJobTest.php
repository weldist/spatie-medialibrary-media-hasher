<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Weldist\Spatie\MediaLibrary\MediaHasher\Jobs\HashMediaJob;
use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Concerns\CreatesMedia;
use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\TestCase;

class HashMediaJobTest extends TestCase
{
    use CreatesMedia;

    #[Test]
    public function a_pending_job_with_the_same_options_is_not_queued_twice(): void
    {
        Queue::fake();

        $media = $this->addMedia($this->makeImageFile());
        $this->artisan('media-library:hash:generate', ['--queue' => true])->assertSuccessful();

        Queue::assertPushed(HashMediaJob::class, 1);
        Queue::assertPushed(HashMediaJob::class, fn (HashMediaJob $job) => $job->mediaId === $media->id);
    }

    #[Test]
    public function jobs_with_different_options_for_the_same_media_are_both_queued(): void
    {
        Queue::fake();

        $this->addMedia($this->makeImageFile());
        $this->artisan('media-library:hash:generate', ['--queue' => true, '--verify' => true])->assertSuccessful();

        Queue::assertPushed(HashMediaJob::class, 2);
    }

    #[Test]
    public function it_takes_the_retry_settings_from_the_config(): void
    {
        config(['media-hasher.queue.tries' => 5, 'media-hasher.queue.backoff' => [1, 2], 'media-hasher.queue.timeout' => 30]);

        $job = new HashMediaJob(1);

        $this->assertSame(5, $job->tries);
        $this->assertSame([1, 2], $job->backoff);
        $this->assertSame(30, $job->timeout);
    }

    #[Test]
    public function null_retry_settings_fall_back_to_the_worker_defaults(): void
    {
        config(['media-hasher.queue.tries' => null, 'media-hasher.queue.backoff' => null, 'media-hasher.queue.timeout' => null]);

        $job = new HashMediaJob(1);

        $this->assertNull($job->tries);
        $this->assertNull($job->backoff);
        $this->assertNull($job->timeout);
    }

    #[Test]
    public function the_unique_id_ignores_the_order_of_the_hashers(): void
    {
        $this->assertSame(
            (new HashMediaJob(7, ['sha256', 'perceptual']))->uniqueId(),
            (new HashMediaJob(7, ['perceptual', 'sha256']))->uniqueId(),
        );
    }
}
