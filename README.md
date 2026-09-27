# weldist/spatie-medialibrary-media-hasher

[![Tests](https://github.com/weldist/spatie-medialibrary-media-hasher/actions/workflows/tests.yml/badge.svg)](https://github.com/weldist/spatie-medialibrary-media-hasher/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/PHP-8.4%2B-blue)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12%20|%2013-red)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE.md)

Computes one or more hashes of every file added to [spatie/laravel-medialibrary](https://github.com/spatie/laravel-medialibrary) in the background and stores them in the media custom properties.

> A [weld.ist](https://weld.ist) project.
>
> Unofficial plugin. Not affiliated with Spatie.

## The Problem

media-library stores files but knows nothing about their content. Questions like "have we stored this exact file before?" or "is this image a resized copy of one we already have?" require a hash, and computing it by hand means:

- **Re-reading every file** from its disk (often remote) whenever the question comes up.
- **Racing other writers** of `custom_properties`: a naive `setCustomProperty()->save()` rewrites the whole JSON column and silently drops keys another process wrote in the meantime.
- **Blocking the request** that added the file, since hashing large files or decoding images is not free.

## The Solution

```php
$model->addMedia($file)->toMediaCollection('photos');
// … a queued job later stores:
// custom_properties.hash = ['sha256' => '9f86d0…', 'perceptual' => '1fb466c2ce476160']
```

- Listens to media-library's `MediaHasBeenAddedEvent` and dispatches a queued job; the request that added the file is not slowed down.
- Runs any number of hashers side by side. Each one stores its value under its own name, and a hasher that does not support the file (e.g. a perceptual hash for a PDF) is simply skipped.
- Writes only the hash property, inside a row-locked transaction, so other custom properties changed concurrently are kept.
- Fires `hashing` / `hashed` / `hashesRemoved` events both as event classes and as Eloquent model events, so observers can hook in.
- Ships Artisan commands to inspect, generate, verify and remove the hashes of media that already exists.

## Requirements

- PHP ^8.4
- Laravel ^12.0 | ^13.0
- `spatie/laravel-medialibrary ^11.0 | ^12.0`
- `ext-imagick` for `PerceptualHasher`

## Installation

```bash
composer require weldist/spatie-medialibrary-media-hasher
```

The service provider is auto-discovered. Publish the config to change the defaults:

```bash
php artisan vendor:publish --tag=media-hasher-config
```

## Configuration

```php
// config/media-hasher.php
return [
    'property' => 'hash',

    'hashers' => [
        'sha256' => Sha256Hasher::class,
        'perceptual' => PerceptualHasher::class,
    ],

    'hash_on_add' => true,

    'collections' => ['*'],

    'queue' => [
        'connection' => env('MEDIA_HASHER_QUEUE_CONNECTION'),
        'name' => env('MEDIA_HASHER_QUEUE'),
        'tries' => 3,
        'backoff' => [10, 60, 300],
        'timeout' => 120,
    ],
];
```

| Key | Meaning |
|---|---|
| `property` | Custom property the hashes are stored under. |
| `hashers` | Hashers to run, keyed by the name their value is stored under. |
| `hash_on_add` | Hash files automatically when they are added. When `false`, only the command hashes. |
| `collections` | Collections hashed automatically. `['*']` hashes every collection, an empty array hashes none. |
| `queue.connection`, `queue.name` | Where `HashMediaJob` is dispatched. `null` uses the application defaults. |
| `queue.tries`, `queue.backoff`, `queue.timeout` | Retry attempts, seconds between retries (an integer or one value per retry) and seconds a run may take. `null` uses the worker defaults. Keep the timeout below the connection's `retry_after`. |

`HashMediaJob` is unique per media and options until it starts processing, so the listener and `media-library:hash:generate --queue` do not queue the same work twice. Unique jobs need a cache store that supports locks.

## Built-in Hashers

| Class | Supports | Value |
|---|---|---|
| `Sha256Hasher` | Every file | 64 hex characters. Identical bytes produce identical hashes. |
| `PerceptualHasher` | Raster images (not SVG) | 16 hex characters (64-bit dHash). Visually identical images produce the same or a nearby hash regardless of format, size or re-encoding. |

Compare perceptual hashes by their Hamming distance; a distance of up to about 6 bits usually means the same picture:

```php
use Weldist\Spatie\MediaLibrary\MediaHasher\Hashers\PerceptualHasher;

PerceptualHasher::distance($first->getHash('perceptual'), $second->getHash('perceptual')); // 0 = identical
```

## Custom Hashers

Implement the `Hasher` contract and register it under a name:

```php
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Weldist\Spatie\MediaLibrary\MediaHasher\Contracts\Hasher;

class Md5Hasher implements Hasher
{
    public function supports(Media $media): bool
    {
        return true;
    }

    public function hash(string $path): string
    {
        return md5_file($path);
    }
}
```

```php
'hashers' => [
    'sha256' => Sha256Hasher::class,
    'md5' => Md5Hasher::class,
],
```

`$path` is a local temporary copy of the file, so hashers work the same for local and remote disks. The file is copied once per run, no matter how many hashers use it. Hashers are resolved from the container.

## Reading Hashes

Add the `InteractsWithHashes` trait to your media model (the one set in `media-library.media_model`):

```php
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;
use Weldist\Spatie\MediaLibrary\MediaHasher\Concerns\InteractsWithHashes;

class Media extends BaseMedia
{
    use InteractsWithHashes;
}
```

```php
$media->getHash('sha256');   // ?string
$media->getHashes();         // ['sha256' => '…', 'perceptual' => '…']
$media->hasHash('perceptual');

Media::query()->whereHash('sha256', $checksum)->get();
```

The trait is optional for hashing itself; it is required for the accessors and for observer support (see below).

## Events

Every run fires, in order:

| Event | When | Cancellable |
|---|---|---|
| `eloquent.hashing: {MediaModel}` model event | Before the file is read | Return `false` |
| `Events\MediaHashing` (`$media`, `$hashers`) | Before the file is read | Return `false` |
| `eloquent.hashed: {MediaModel}` model event | After the hashes are stored | — |
| `Events\MediaHashed` (`$media`, `$hashes`) | After the hashes are stored | — |

`$hashers` lists the hasher names about to run; `$hashes` contains only the values computed in this run.

```php
use Weldist\Spatie\MediaLibrary\MediaHasher\Events\MediaHashed;

Event::listen(MediaHashed::class, function (MediaHashed $event) {
    // $event->media, $event->hashes
});
```

### Observers

With the `InteractsWithHashes` trait on the media model, observers can define `hashing`, `hashed` and `hashesRemoved` methods, and closures can be registered statically:

```php
class MediaObserver
{
    public function hashing(Media $media): ?bool
    {
        return $media->size > 0 ? null : false; // false cancels
    }

    public function hashed(Media $media): void
    {
        // …
    }
}

Media::observe(MediaObserver::class);

Media::hashed(fn (Media $media) => /* … */);
Media::hashesRemoved(fn (Media $media) => /* … */);
```

Removing hashes (see the `clean` and `clear` commands, or `MediaHasher::forget()`) fires the `eloquent.hashesRemoved: {MediaModel}` model event and `Events\MediaHashesRemoved` (`$media`, `$hashers`) with the names of the removed hashes.

### Why the hashes are not saved with `save()`

Hashes are written with a single JSON-path update of the hash property. Other custom properties are never rewritten, so a concurrent `setCustomProperty()->save()` elsewhere cannot be lost, and vice versa. As a consequence the regular `saving` / `updated` model events (and anything built on them, such as activity logs) are not fired for this write; use the hash events instead. The in-memory model passed to the events already contains the change and is not left dirty.

The write is covered by the test suite on SQLite and verified on MySQL 8.4. MariaDB, PostgreSQL and SQL Server have their own JSON expressions but are not part of the test suite yet.

## Commands

All commands accept the same filters:

| Option | Meaning |
|---|---|
| `--collection=*` | Only include these collections. |
| `--model=*` | Only include media of these model classes. |
| `--id-from=`, `--id-to=` | Limit by media id range. |
| `--chunk=100` | Rows per database chunk. |

### `media-library:hash:status`

Read-only. Shows, per configured hasher, how many media have the hash stored, are missing it, or are not supported by the hasher, and lists stored hashes whose hasher is no longer configured.

### `media-library:hash:generate`

Computes and stores the missing hashes of existing media.

| Option | Meaning |
|---|---|
| `--hasher=*` | Only run these configured hashers. |
| `--force` | Recompute and store hashes that are already stored. |
| `--verify` | Recompute every hash and store only the ones that are missing or no longer match the file, e.g. after the file was re-encoded in place. |
| `--queue` | Dispatch a `HashMediaJob` per row instead of hashing synchronously. |

Only missing hashes are computed unless `--force` or `--verify` is given, so adding a new hasher later and running the command fills in just that hasher.

### `media-library:hash:clean`

Removes the stored hashes of hashers that are no longer configured. `--dry-run` shows what would be removed.

### `media-library:hash:clear`

Removes stored hashes whether their hasher is configured or not. Without options every hash is removed; `--hasher=*` limits it to the given names. Asks for confirmation unless `--force` is given.

Not to be confused with media-library's own `media-library:clean`, which removes orphaned files and conversions.

## Testing

```bash
docker compose --profile php84 up --build --abort-on-container-exit
docker compose --profile php85 up --build --abort-on-container-exit
```

## License

MIT
