<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Feature;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Weldist\Spatie\MediaLibrary\MediaHasher\Events\MediaHashed;
use Weldist\Spatie\MediaLibrary\MediaHasher\Events\MediaHashing;
use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Concerns\CreatesMedia;
use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Support\RecordingObserver;
use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Support\TestMedia;
use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\TestCase;

class HashEventsTest extends TestCase
{
    use CreatesMedia;

    protected function setUp(): void
    {
        parent::setUp();

        RecordingObserver::$calls = [];
        RecordingObserver::$cancelHashing = false;
    }

    #[Test]
    public function it_dispatches_the_hashing_and_hashed_events(): void
    {
        $hashing = [];
        $hashed = [];
        Event::listen(MediaHashing::class, function (MediaHashing $event) use (&$hashing): void {
            $hashing[] = $event->hashers;
        });
        Event::listen(MediaHashed::class, function (MediaHashed $event) use (&$hashed): void {
            $hashed[] = $event->hashes;
        });

        $path = $this->makeImageFile();
        $this->addMedia($path);

        $this->assertSame([['sha256', 'perceptual']], $hashing);
        $this->assertSame(hash_file('sha256', $path), $hashed[0]['sha256']);
    }

    #[Test]
    public function a_hashing_listener_returning_false_cancels_hashing(): void
    {
        $hashed = false;
        Event::listen(MediaHashing::class, fn () => false);
        Event::listen(MediaHashed::class, function () use (&$hashed): void {
            $hashed = true;
        });

        $media = $this->addMedia($this->makeImageFile())->refresh();

        $this->assertSame([], $media->getHashes());
        $this->assertFalse($hashed);
    }

    #[Test]
    public function observers_receive_the_hashing_and_hashed_model_events(): void
    {
        TestMedia::observe(RecordingObserver::class);

        $media = $this->addMedia($this->makeImageFile());

        $this->assertSame(["hashing:{$media->id}", "hashed:{$media->id}"], RecordingObserver::$calls);
    }

    #[Test]
    public function an_observer_returning_false_from_hashing_cancels_hashing(): void
    {
        RecordingObserver::$cancelHashing = true;
        TestMedia::observe(RecordingObserver::class);

        $media = $this->addMedia($this->makeImageFile())->refresh();

        $this->assertSame([], $media->getHashes());
        $this->assertSame(["hashing:{$media->id}"], RecordingObserver::$calls);
    }

    #[Test]
    public function closures_can_be_registered_through_the_static_model_event_methods(): void
    {
        $hashedIds = [];
        TestMedia::hashed(function (TestMedia $media) use (&$hashedIds): void {
            $hashedIds[] = $media->id;
        });

        $media = $this->addMedia($this->makeImageFile());

        $this->assertSame([$media->id], $hashedIds);
    }
}
