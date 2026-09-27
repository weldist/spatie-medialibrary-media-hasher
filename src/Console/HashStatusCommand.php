<?php

declare(strict_types=1);

namespace Weldist\Spatie\MediaLibrary\MediaHasher\Console;

use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Weldist\Spatie\MediaLibrary\MediaHasher\Console\Concerns\FiltersMedia;
use Weldist\Spatie\MediaLibrary\MediaHasher\MediaHasher;

class HashStatusCommand extends Command
{
    use FiltersMedia;

    protected $signature = 'media-library:hash:status'.self::MEDIA_FILTERS;

    protected $description = 'Show how many media have, miss or do not support each configured hash.';

    public function handle(MediaHasher $mediaHasher): int
    {
        $hashers = $mediaHasher->hashers();
        $property = config('media-hasher.property');
        $counts = array_fill_keys(array_keys($hashers), ['stored' => 0, 'missing' => 0, 'unsupported' => 0]);
        $unconfigured = [];

        $total = $this->eachMedia($this->mediaQuery(), function (Media $media) use ($hashers, $property, &$counts, &$unconfigured): void {
            $stored = (array) $media->getCustomProperty($property, []);

            foreach ($hashers as $name => $hasher) {
                $counts[$name][match (true) {
                    isset($stored[$name]) => 'stored',
                    $hasher->supports($media) => 'missing',
                    default => 'unsupported',
                }]++;
            }

            foreach (array_keys(array_diff_key($stored, $hashers)) as $name) {
                $unconfigured[$name] = ($unconfigured[$name] ?? 0) + 1;
            }
        });

        $this->info("{$total} media row(s) inspected.");

        $this->table(
            ['Hasher', 'Stored', 'Missing', 'Unsupported'],
            array_map(fn (string $name, array $count): array => [$name, ...array_values($count)], array_keys($counts), $counts),
        );

        if ($unconfigured !== []) {
            $this->newLine();
            $this->warn('Hashes of hashers that are no longer configured:');
            $this->table(['Hash', 'Media'], array_map(null, array_keys($unconfigured), $unconfigured));
            $this->line('Remove them with: php artisan media-library:hash:clean');
        }

        return self::SUCCESS;
    }
}
