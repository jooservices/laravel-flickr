<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | OAuth HTTP callback path
    |--------------------------------------------------------------------------
    |
    | Boot-bound: registered as a route at provider boot. All other Flickr
    | settings live in jooservices/laravel-config under the `flickr` group.
    |
    */
    'oauth' => [
        'callback_path' => env('FLICKR_OAUTH_CALLBACK_PATH', 'api/v1/oauth/flickr/callback'),
    ],

];
