<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Weldist\Spatie\MediaLibrary\MediaHasher\Jobs\HashMediaJob;
use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Concerns\CreatesMedia;
use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Support\TestMedia;
use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\TestCase;

class HashGenerateCommandTest extends TestCase
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
        $image = $this->addMedia($this->makeImageFile());
        $text = $this->addMedia($this->makeTextFile());

        $this->artisan('media-library:hash:generate')->assertSuccessful();

        $this->assertSame(['sha256', 'perceptual'], array_keys($image->fresh()->getHashes()));
        $this->assertSame(['sha256'], array_keys($text->fresh()->getHashes()));
    }

    #[Test]
    public function it_limits_to_the_given_hashers_and_collections(): void
    {
        $avatar = $this->addMedia($this->makeImageFile(), 'avatars');
        $document = $this->addMedia($this->makeImageFile(), 'documents');

        $this->artisan('media-library:hash:generate', ['--hasher' => ['sha256'], '--collection' => ['avatars']])->assertSuccessful();

        $this->assertSame(['sha256'], array_keys($avatar->fresh()->getHashes()));
        $this->assertSame([], $document->fresh()->getHashes());
    }

    #[Test]
    public function it_updates_only_changed_hashes_with_the_verify_option(): void
    {
        $path = $this->makeImageFile();
        $media = $this->addMedia($path);
        $this->artisan('media-library:hash:generate')->assertSuccessful();
        $perceptual = $media->fresh()->getHash('perceptual');

        $media = $media->fresh();
        $media->setCustomProperty('hash.sha256', 'outdated')->save();

        $this->artisan('media-library:hash:generate', ['--verify' => true])
            ->expectsTable(['Status', 'Count'], [['Updated', 1], ['Skipped', 0], ['Failed', 0]])
            ->assertSuccessful();

        $this->assertSame(hash_file('sha256', $path), $media->fresh()->getHash('sha256'));
        $this->assertSame($perceptual, $media->fresh()->getHash('perceptual'));
    }

    #[Test]
    public function it_dispatches_one_job_per_media_with_the_queue_option(): void
    {
        $media = $this->addMedia($this->makeImageFile());
        Queue::fake();

        $this->artisan('media-library:hash:generate', ['--queue' => true, '--hasher' => ['perceptual'], '--verify' => true])->assertSuccessful();

        Queue::assertPushed(HashMediaJob::class, fn (HashMediaJob $job) => $job->mediaId === $media->id
            && $job->hashers === ['perceptual']
            && $job->verify
            && ! $job->force);
    }

    #[Test]
    public function it_fails_for_unknown_hashers(): void
    {
        $this->artisan('media-library:hash:generate', ['--hasher' => ['md5']])->assertFailed();

        $this->assertSame(0, TestMedia::query()->count());
    }

    #[Test]
    public function it_rejects_combining_force_and_verify(): void
    {
        $this->artisan('media-library:hash:generate', ['--force' => true, '--verify' => true])->assertFailed();
    }
}
