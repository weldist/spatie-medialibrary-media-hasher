<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Feature;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Weldist\Spatie\MediaLibrary\MediaHasher\Hashers\PerceptualHasher;
use Weldist\Spatie\MediaLibrary\MediaHasher\MediaHasher;
use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Concerns\CreatesMedia;
use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\Support\TestMedia;
use Weldist\Spatie\MediaLibrary\MediaHasher\Tests\TestCase;

class MediaHasherTest extends TestCase
{
    use CreatesMedia;

    #[Test]
    public function it_keeps_custom_properties_written_by_others_in_the_meantime(): void
    {
        $media = $this->addMediaWithoutHashes($this->makeImageFile());

        DB::table('media')->where('id', $media->id)->update(['custom_properties' => json_encode(['caption' => 'written elsewhere'])]);

        app(MediaHasher::class)->hash($media);

        $fresh = $media->fresh();
        $this->assertSame('written elsewhere', $fresh->getCustomProperty('caption'));
        $this->assertNotNull($fresh->getHash('sha256'));
    }

    #[Test]
    public function it_only_computes_missing_hashes_unless_forced(): void
    {
        $media = $this->addMedia($this->makeImageFile())->refresh();
        $media->setCustomProperty('hash.sha256', 'stale')->save();

        $this->assertSame([], app(MediaHasher::class)->hash($media, ['sha256']));
        $this->assertSame('stale', $media->fresh()->getHash('sha256'));

        $hashes = app(MediaHasher::class)->hash($media, ['sha256'], force: true);

        $this->assertSame(['sha256'], array_keys($hashes));
        $this->assertNotSame('stale', $media->fresh()->getHash('sha256'));
        $this->assertNotNull($media->fresh()->getHash('perceptual'));
    }

    #[Test]
    public function it_syncs_the_in_memory_model_without_leaving_it_dirty(): void
    {
        $media = $this->addMediaWithoutHashes($this->makeImageFile());

        app(MediaHasher::class)->hash($media);

        $this->assertNotNull($media->getHash('sha256'));
        $this->assertFalse($media->isDirty('custom_properties'));
    }

    #[Test]
    public function it_produces_the_same_perceptual_hash_for_re_encoded_copies(): void
    {
        $jpeg = $this->addMedia($this->makeImageFile('jpg'))->refresh();
        $png = $this->addMedia($this->makeImageFile('png'))->refresh();

        $this->assertLessThanOrEqual(2, PerceptualHasher::distance(
            $jpeg->getHash('perceptual'),
            $png->getHash('perceptual'),
        ));
    }

    #[Test]
    public function it_finds_media_by_hash(): void
    {
        $path = $this->makeImageFile();
        $media = $this->addMedia($path);
        $this->addMedia($this->makeTextFile());

        $this->assertSame([$media->id], TestMedia::query()->whereHash('sha256', hash_file('sha256', $path))->pluck('id')->all());
    }

    #[Test]
    #[DefineEnvironment('useCustomProperty')]
    public function it_stores_hashes_under_the_configured_property(): void
    {
        $media = $this->addMedia($this->makeTextFile())->refresh();

        $this->assertNotNull($media->getCustomProperty('checksums.sha256'));
        $this->assertFalse($media->hasCustomProperty('hash'));
    }

    #[Test]
    public function it_rejects_hashers_that_do_not_implement_the_contract(): void
    {
        config(['media-hasher.hashers' => ['invalid' => stdClass::class]]);

        $this->expectException(InvalidArgumentException::class);

        app(MediaHasher::class)->hashers();
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function useCustomProperty($app): void
    {
        $app['config']->set('media-hasher.property', 'checksums');
    }

    private function addMediaWithoutHashes(string $path): TestMedia
    {
        config(['media-hasher.collections' => ['none']]);

        $media = $this->addMedia($path);

        config(['media-hasher.collections' => []]);

        return $media->refresh();
    }
}
