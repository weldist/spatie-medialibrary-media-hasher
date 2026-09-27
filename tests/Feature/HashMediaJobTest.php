<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Feature;

use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Weldist\Spatie\MediaLibrary\MediaHasher\Exceptions\MediaFileNotFound;
use Weldist\Spatie\MediaLibrary\MediaHasher\Jobs\HashMediaJob;
use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Concerns\CreatesMedia;
use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Support\TestMedia;
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
    public function a_missing_file_is_reported_without_failing_the_job(): void
    {
        Exceptions::fake();
        $media = $this->mediaWithMissingFile();

        dispatch_sync(new HashMediaJob($media->id));

        Exceptions::assertReported(fn (MediaFileNotFound $e) => str_contains($e->getMessage(), "media #{$media->id}"));
        $this->assertSame([], $media->fresh()->getHashes());
    }

    #[Test]
    public function the_generate_command_counts_a_missing_file_as_failed(): void
    {
        $this->mediaWithMissingFile();

        $this->artisan('media-library:hash:generate')
            ->expectsTable(['Status', 'Count'], [['Hashed', 0], ['Skipped', 0], ['Failed', 1]])
            ->assertFailed();
    }

    #[Test]
    public function the_unique_id_ignores_the_order_of_the_hashers(): void
    {
        $this->assertSame(
            (new HashMediaJob(7, ['sha256', 'perceptual']))->uniqueId(),
            (new HashMediaJob(7, ['perceptual', 'sha256']))->uniqueId(),
        );
    }

    private function mediaWithMissingFile(): TestMedia
    {
        config(['media-hasher.collections' => []]);
        $media = $this->addMedia($this->makeImageFile());
        config(['media-hasher.collections' => ['*']]);

        Storage::disk($media->disk)->delete($media->getPathRelativeToRoot());

        return $media;
    }
}
