<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use Weldist\Spatie\MediaLibrary\MediaHasher\Jobs\HashMediaJob;
use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Concerns\CreatesMedia;
use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\TestCase;

class HashOnAddTest extends TestCase
{
    use CreatesMedia;

    #[Test]
    public function it_stores_every_supported_hash_when_media_is_added(): void
    {
        $path = $this->makeImageFile();

        $media = $this->addMedia($path)->refresh();

        $this->assertSame(hash_file('sha256', $path), $media->getHash('sha256'));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $media->getHash('perceptual'));
        $this->assertSame(['sha256', 'perceptual'], array_keys($media->getHashes()));
    }

    #[Test]
    public function it_skips_hashers_that_do_not_support_the_file(): void
    {
        $media = $this->addMedia($this->makeTextFile())->refresh();

        $this->assertNotNull($media->getHash('sha256'));
        $this->assertFalse($media->hasHash('perceptual'));
    }

    #[Test]
    public function it_dispatches_the_job_on_the_configured_queue(): void
    {
        Queue::fake();
        config(['media-hasher.queue' => ['connection' => 'redis', 'name' => 'hashing']]);

        $media = $this->addMedia($this->makeImageFile());

        Queue::assertPushed(HashMediaJob::class, fn (HashMediaJob $job) => $job->mediaId === $media->id
            && $job->connection === 'redis'
            && $job->queue === 'hashing');
    }

    #[Test]
    public function it_only_hashes_the_configured_collections(): void
    {
        config(['media-hasher.collections' => ['avatars']]);

        $ignored = $this->addMedia($this->makeImageFile(), 'documents')->refresh();
        $hashed = $this->addMedia($this->makeImageFile(), 'avatars')->refresh();

        $this->assertSame([], $ignored->getHashes());
        $this->assertNotNull($hashed->getHash('sha256'));
    }

    #[Test]
    public function it_hashes_every_collection_with_the_wildcard(): void
    {
        config(['media-hasher.collections' => ['avatars', '*']]);

        $media = $this->addMedia($this->makeImageFile(), 'documents')->refresh();

        $this->assertNotNull($media->getHash('sha256'));
    }

    #[Test]
    public function it_hashes_no_collection_when_the_list_is_empty(): void
    {
        Queue::fake();
        config(['media-hasher.collections' => []]);

        $this->addMedia($this->makeImageFile());

        Queue::assertNothingPushed();
    }

    #[Test]
    #[DefineEnvironment('disableHashOnAdd')]
    public function it_does_not_hash_on_add_when_disabled(): void
    {
        Queue::fake();

        $this->addMedia($this->makeImageFile());

        Queue::assertNothingPushed();
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function disableHashOnAdd($app): void
    {
        $app['config']->set('media-hasher.hash_on_add', false);
    }
}
