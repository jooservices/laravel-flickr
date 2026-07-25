<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Facades;

use Illuminate\Support\Facades\Facade;
use JOOservices\Flickr\Flickr as FlickrSdk;
use JOOservices\LaravelFlickr\RateLimit\RateLimitStatus;
use JOOservices\LaravelFlickr\Service\FlickrService;

/**
 * @method static FlickrService connection(string $name)
 * @method static FlickrService as(string $nsid)
 * @method static FlickrService anonymous()
 * @method static mixed call(string $namespace, string $method, array<string, mixed> $params = [])
 * @method static FlickrSdk getClient()
 * @method static RateLimitStatus rateLimitStatus()
 *
 * @see FlickrService
 */
final class Flickr extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FlickrService::class;
    }
}
