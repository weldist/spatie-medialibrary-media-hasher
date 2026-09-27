<?php

declare(strict_types=1);

use Weldist\Spatie\MediaLibrary\MediaHasher\Hashers\PerceptualHasher;
use Weldist\Spatie\MediaLibrary\MediaHasher\Hashers\Sha256Hasher;

return [

    /*
     * The custom property the hashes are stored under. Every enabled hasher
     * writes its value to a nested key: custom_properties.{property}.{hasher}
     */
    'property' => 'hash',

    /*
     * The hashers to run, keyed by the name their value is stored under.
     * Each class must implement the Hasher contract. Remove an entry to
     * disable it; PerceptualHasher requires ext-imagick.
     */
    'hashers' => [
        'sha256' => Sha256Hasher::class,
        // 'perceptual' => PerceptualHasher::class,
    ],

    /*
     * Hash files automatically when they are added to the media library.
     * When disabled, hashes are only computed by the media-library:hash:generate command.
     */
    'hash_on_add' => true,

    /*
     * The collection names hashed automatically. Use '*' for every collection;
     * an empty array hashes none.
     */
    'collections' => ['*'],

    /*
     * The queue the hashing job is dispatched on and how it is retried.
     * Null uses the application or worker defaults. The timeout (seconds) must
     * stay below the retry_after value of the queue connection.
     */
    'queue' => [
        'connection' => env('MEDIA_HASHER_QUEUE_CONNECTION'),
        'name' => env('MEDIA_HASHER_QUEUE'),
        'tries' => 3,
        'backoff' => [10, 60, 300],
        'timeout' => 120,
    ],

];
