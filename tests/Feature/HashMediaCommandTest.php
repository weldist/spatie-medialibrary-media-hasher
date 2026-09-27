<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Weldist\Spatie\MediaLibrary\MediaHasher\Jobs\HashMediaJob;
use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Concerns\CreatesMedia;
use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Support\TestMedia;
use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\TestCase;

class HashMediaCommandTest extends TestCase
{
    use CreatesMedia;

    protected function setUp(): void
    {
        parent::setUp();

        config(['media-hasher.collections' => ['nothing-is-hashed-on-add']]);
    }

    #[Test]
    public function it_hashes_existing_media_synchronously(): void
    {
        $first = $this->addMedia($this->makeImageFile());
        $second = $this->addMedia($this->makeTextFile());

        $this->artisan('media-library:hash')->assertSuccessful();

        $this->assertNotNull($first->fresh()->getHash('perceptual'));
        $this->assertNotNull($second->fresh()->getHash('sha256'));
    }

    #[Test]
    public function it_limits_to_the_given_hashers_and_collections(): void
    {
        $avatar = $this->addMedia($this->makeImageFile(), 'avatars');
        $document = $this->addMedia($this->makeImageFile(), 'documents');

        $this->artisan('media-library:hash', ['--hasher' => ['sha256'], '--collection' => ['avatars']])->assertSuccessful();

        $this->assertSame(['sha256'], array_keys($avatar->fresh()->getHashes()));
        $this->assertSame([], $document->fresh()->getHashes());
    }

    #[Test]
    public function it_dispatches_one_job_per_media_with_the_queue_option(): void
    {
        $media = $this->addMedia($this->makeImageFile());
        Queue::fake();

        $this->artisan('media-library:hash', ['--queue' => true, '--hasher' => ['perceptual'], '--force' => true])->assertSuccessful();

        Queue::assertPushed(HashMediaJob::class, fn (HashMediaJob $job) => $job->mediaId === $media->id
            && $job->hashers === ['perceptual']
            && $job->force);
    }

    #[Test]
    public function it_fails_for_unknown_hashers(): void
    {
        $this->artisan('media-library:hash', ['--hasher' => ['md5']])->assertFailed();

        $this->assertSame(0, TestMedia::query()->count());
    }
}
