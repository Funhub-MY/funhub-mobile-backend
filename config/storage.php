<?php

return [

    'cloud_disks' => ['hwc_obs', 's3'],

    'public_disk_map' => [
        'hwc_obs' => 'hwc_obs_public',
        's3' => 's3_public',
    ],

    'legacy_disk_aliases' => [
        's3' => 'hwc_obs',
        's3_public' => 'hwc_obs_public',
    ],

    'url_replace_from' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('STORAGE_URL_REPLACE_FROM', ''))
    ))),

    'url_replace_to' => rtrim(env('STORAGE_URL_REPLACE_TO', env('HWC_OBS_URL', '')), '/'),

    'legacy_url_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('STORAGE_LEGACY_URL_HOSTS', 'amazonaws.com,cloudfront.net'))
    ))),

];
