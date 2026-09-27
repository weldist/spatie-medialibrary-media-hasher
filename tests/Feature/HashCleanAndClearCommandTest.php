<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Concerns\CreatesMedia;
use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Support\TestMedia;
use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\TestCase;

class HashCleanAndClearCommandTest extends TestCase
{
    use CreatesMedia;

    #[Test]
    public function clean_removes_only_the_hashes_of_unconfigured_hashers(): void
    {
        $media = $this->mediaWithLegacyHash();

        $this->artisan('media-library:hash:clean')
            ->expectsTable(['Hash', 'Removed from media'], [['md5', 1]])
            ->assertSuccessful();

        $this->assertSame(['sha256', 'perceptual'], array_keys($media->fresh()->getHashes()));
    }

    #[Test]
    public function clean_does_not_modify_media_in_dry_run(): void
    {
        $media = $this->mediaWithLegacyHash();

        $this->artisan('media-library:hash:clean', ['--dry-run' => true])
            ->expectsTable(['Hash', 'Would remove from media'], [['md5', 1]])
            ->assertSuccessful();

        $this->assertSame('legacy', $media->fresh()->getHash('md5'));
    }

    #[Test]
    public function clear_removes_every_hash_and_the_property_itself_after_confirmation(): void
    {
        $media = $this->mediaWithLegacyHash();

        $this->artisan('media-library:hash:clear')
            ->expectsConfirmation('This removes all stored hashes of the matching media. Continue?', 'yes')
            ->assertSuccessful();

        $fresh = $media->fresh();
        $this->assertFalse($fresh->hasCustomProperty('hash'));
        $this->assertSame('kept', $fresh->getCustomProperty('caption'));
    }

    #[Test]
    public function clear_removes_only_the_given_hashers_configured_or_not(): void
    {
        $media = $this->mediaWithLegacyHash();

        $this->artisan('media-library:hash:clear', ['--hasher' => ['md5', 'perceptual'], '--force' => true])->assertSuccessful();

        $this->assertSame(['sha256'], array_keys($media->fresh()->getHashes()));
    }

    #[Test]
    public function clear_aborts_when_not_confirmed(): void
    {
        $media = $this->mediaWithLegacyHash();

        $this->artisan('media-library:hash:clear')
            ->expectsConfirmation('This removes all stored hashes of the matching media. Continue?', 'no')
            ->assertFailed();

        $this->assertCount(3, $media->fresh()->getHashes());
    }

    private function mediaWithLegacyHash(): TestMedia
    {
        $media = $this->addMedia($this->makeImageFile())->refresh();
        $media->setCustomProperty('hash.md5', 'legacy')->setCustomProperty('caption', 'kept')->save();

        return $media;
    }
}
