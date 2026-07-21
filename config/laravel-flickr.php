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

    'rate_limit' => [
        'enabled' => env('FLICKR_RATE_LIMIT_ENABLED', true),
        'min_gap_ms' => (int) env('FLICKR_RATE_LIMIT_MIN_GAP_MS', 333),
        'window_seconds' => (int) env('FLICKR_RATE_LIMIT_WINDOW_SECONDS', 3600),
        'max_requests_per_hour' => (int) env('FLICKR_RATE_LIMIT_MAX_PER_HOUR', 3500),
        'cooldown_seconds' => (int) env('FLICKR_RATE_LIMIT_COOLDOWN_SECONDS', 3600),
        'key_prefix' => env('FLICKR_RATE_LIMIT_KEY_PREFIX', 'laravel-flickr:req'),
    ],

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
