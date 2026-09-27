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
    public function the_unique_id_ignores_the_order_of_the_hashers(): void
    {
        $this->assertSame(
            (new HashMediaJob(7, ['sha256', 'perceptual']))->uniqueId(),
            (new HashMediaJob(7, ['perceptual', 'sha256']))->uniqueId(),
        );
    }
}
