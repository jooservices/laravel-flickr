<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Flickr application credentials
    |--------------------------------------------------------------------------
    |
    | Used by ConfigCredentialsResolver and factory helpers that read config.
    | Host apps may ignore these and pass AppCredentials explicitly.
    |
    */
    'api_key' => env('FLICKR_API_KEY', ''),
    'api_secret' => env('FLICKR_API_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Default page size for fetch helpers
    |--------------------------------------------------------------------------
    */
    'default_per_page' => (int) env('FLICKR_DEFAULT_PER_PAGE', 100),

    /*
    |--------------------------------------------------------------------------
    | Default crawl-style query defaults (optional product-neutral filters)
    |--------------------------------------------------------------------------
    */
    'people_photos' => [
        'extras' => env(
            'FLICKR_PEOPLE_PHOTOS_EXTRAS',
            'date_upload,date_taken,owner_name,original_format,url_o,url_k,url_h,url_l,url_c,url_z,url_m',
        ),
        'safe_search' => (int) env('FLICKR_PEOPLE_PHOTOS_SAFE_SEARCH', 3),
        'content_type' => (int) env('FLICKR_PEOPLE_PHOTOS_CONTENT_TYPE', 7),
        'privacy_filter' => (int) env('FLICKR_PEOPLE_PHOTOS_PRIVACY_FILTER', 1),
    ],
];
