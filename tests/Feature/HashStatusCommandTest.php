<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Concerns\CreatesMedia;
use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\TestCase;

class HashStatusCommandTest extends TestCase
{
    use CreatesMedia;

    #[Test]
    public function it_reports_stored_missing_unsupported_and_unconfigured_hashes(): void
    {
        $image = $this->addMedia($this->makeImageFile())->refresh();
        $this->addMedia($this->makeTextFile());

        $image->setCustomProperty('hash.md5', 'legacy')->forgetCustomProperty('hash.perceptual')->save();

        $this->artisan('media-library:hash:status')
            ->expectsOutput('2 media row(s) inspected.')
            ->expectsTable(['Hasher', 'Stored', 'Missing', 'Unsupported'], [
                ['sha256', 2, 0, 0],
                ['perceptual', 0, 1, 1],
            ])
            ->expectsTable(['Hash', 'Media'], [['md5', 1]])
            ->assertSuccessful();
    }

    #[Test]
    public function it_does_not_modify_media(): void
    {
        config(['media-hasher.collections' => ['nothing-is-hashed-on-add']]);
        $media = $this->addMedia($this->makeImageFile());

        $this->artisan('media-library:hash:status')->assertSuccessful();

        $this->assertSame([], $media->fresh()->getHashes());
    }
}
