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
     * Limit automatic hashing to these collection names. Leave empty for all collections.
     */
    'collections' => [],

    /*
     * The queue connection and name the hashing job is dispatched on.
     * Null uses the application defaults.
     */
    'queue_connection' => env('MEDIA_HASHER_QUEUE_CONNECTION'),

    'queue_name' => env('MEDIA_HASHER_QUEUE'),

];
