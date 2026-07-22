<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use JOOservices\LaravelFlickr\Http\Controllers\OAuthCallbackController;

$callbackPath = config('laravel-flickr.oauth.callback_path', 'api/v1/oauth/flickr/callback');
$callbackPath = is_string($callbackPath) && $callbackPath !== '' ? $callbackPath : 'api/v1/oauth/flickr/callback';

Route::get($callbackPath, OAuthCallbackController::class)
    ->middleware('throttle:30,1')
    ->name('laravel-flickr.oauth.callback');
