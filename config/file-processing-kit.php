<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default storage disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk used by the storage handler when none is given
    | explicitly. Leave null to fall back to the application default disk
    | (config('filesystems.default')). Set it to an env() value in your own
    | published config if you want it driven by the environment.
    |
    */

    'disk' => null,

    /*
    |--------------------------------------------------------------------------
    | Image handling
    |--------------------------------------------------------------------------
    |
    | When "auto_scale" is enabled, any file detected as an image is scaled
    | down proportionally to fit within max_width x max_height before it is
    | stored or read out. Scaling never enlarges an image beyond its original
    | size and always preserves the aspect ratio.
    |
    | "driver" selects the intervention/image backend: "gd" or "imagick".
    |
    */

    'images' => [
        'auto_scale' => true,
        'max_width' => 1920,
        'max_height' => 1080,
        'driver' => 'gd',
    ],

    /*
    |--------------------------------------------------------------------------
    | Compression
    |--------------------------------------------------------------------------
    |
    | The gzip compression level (0-9). Higher means smaller output at the
    | cost of more CPU. 6 is the zlib default.
    |
    */

    'compression' => [
        'level' => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Working files
    |--------------------------------------------------------------------------
    |
    | The pipeline operates on a temporary copy of the source file so the
    | original is never mutated. Working copies live in the system temp
    | directory and are removed when the pending file is disposed. Set an
    | absolute path here to override the location.
    |
    */

    'temp_directory' => null,

    /*
    |--------------------------------------------------------------------------
    | MIME map overrides
    |--------------------------------------------------------------------------
    |
    | Map additional MIME types to a FileCategory, or override the built-in
    | classification. Keys are MIME types, values are FileCategory cases.
    |
    */

    'mime_map' => [
        // 'application/x-custom' => \Scrapkit\FileProcessingKit\Enums\FileCategory::Document,
    ],

];
