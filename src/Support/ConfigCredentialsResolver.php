<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Support;

use Jooservices\LaravelFlickr\Contracts\AppCredentialsResolverInterface;
use Jooservices\LaravelFlickr\Dto\AppCredentials;
use Jooservices\LaravelFlickr\Exceptions\MissingCredentialsException;

final class ConfigCredentialsResolver implements AppCredentialsResolverInterface
{
    public function resolve(): AppCredentials
    {
        $key = config('laravel-flickr.api_key', '');
        $secret = config('laravel-flickr.api_secret', '');
        $key = is_string($key) ? $key : '';
        $secret = is_string($secret) ? $secret : '';

        if (trim($key) === '' || trim($secret) === '') {
            throw MissingCredentialsException::configNotSet();
        }

        return new AppCredentials($key, $secret);
    }
}
