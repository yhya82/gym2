<?php

/*
 * gym2: uploaded media (currently the Settings logo) goes to the "media" disk,
 * which is whichever real disk this environment should use. The code only
 * ever says Storage::disk('media'), so moving between a laptop, a branch and
 * production needs no code changes:
 *
 *   - production: "s3" — the private R2 bucket, shown via signed URLs.
 *   - anywhere without a bucket configured (local dev, CI): "public" —
 *     storage/app/public, served through the public/storage symlink.
 *
 * MEDIA_DISK overrides the automatic choice (e.g. MEDIA_DISK=s3 locally,
 * with a dev bucket, to test signed URLs before deploying).
 */
$s3 = [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
    'url' => env('AWS_URL'),
    'endpoint' => env('AWS_ENDPOINT'),
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
    'throw' => false,
    'report' => false,
];

$mediaDisk = env('MEDIA_DISK', env('AWS_BUCKET') ? 's3' : 'public');

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => $s3,

        // See the note at the top of this file.
        'media' => $mediaDisk === 's3' ? $s3 : [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            // Relative, not built from APP_URL: the same URL then works on
            // localhost, 127.0.0.1:8001 or any other host/port.
            'url' => '/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
