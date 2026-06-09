<?php

$hwcObs = [
    'driver' => 's3',
    'key' => env('HWC_OBS_KEY'),
    'secret' => env('HWC_OBS_SECRET'),
    'region' => env('HWC_OBS_REGION'),
    'bucket' => env('HWC_OBS_BUCKET'),
    'url' => env('HWC_OBS_URL'),
    'endpoint' => env('HWC_OBS_ENDPOINT'),
    'use_path_style_endpoint' => env('HWC_OBS_USE_PATH_STYLE_ENDPOINT', false),
    'throw' => false,
];

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        // Huawei Cloud OBS (primary)
        'hwc_obs' => $hwcObs,

        'hwc_obs_public' => array_merge($hwcObs, [
            'visibility' => 'public',
        ]),

        /*
        | Legacy media.disk values in the database — same Huawei OBS credentials.
        */
        's3' => $hwcObs,

        's3_public' => array_merge($hwcObs, [
            'visibility' => 'public',
        ]),

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
