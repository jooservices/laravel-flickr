<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Tests;

use Jooservices\LaravelFlickr\LaravelFlickrServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelFlickrServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('laravel-flickr.api_key', 'test-api-key');
        $app['config']->set('laravel-flickr.api_secret', 'test-api-secret');
        $app['config']->set('laravel-flickr.default_per_page', 50);
    }
}
