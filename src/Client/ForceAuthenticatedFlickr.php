<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Client;

use JOOservices\Flickr\Contracts\Client\FlickrClientContract;
use JOOservices\Flickr\Flickr;
use JOOservices\Flickr\FlickrFactory;
use JOOservices\Flickr\Services\RawApiService;
use ReflectionProperty;
use RuntimeException;

/**
 * Ensures connection-backed clients always OAuth-sign REST calls.
 *
 * {@see FlickrFactory} does not accept a custom REST client decorator,
 * so this wrapper reflects into {@see RawApiService}'s private client. Fail-closed on
 * unexpected types. Prefer an upstream SDK injection point when available.
 */
final class ForceAuthenticatedFlickr
{
    public static function wrap(Flickr $flickr): Flickr
    {
        $raw = $flickr->raw();
        if (! $raw instanceof RawApiService) {
            throw new RuntimeException('Unexpected Flickr raw API service; cannot force authentication.');
        }

        $property = new ReflectionProperty(RawApiService::class, 'client');
        $inner = $property->getValue($raw);
        // RawApiService::$client is typed FlickrClientContract — invalid values cannot be set at runtime.
        if (! $inner instanceof FlickrClientContract) { // @codeCoverageIgnore
            throw new RuntimeException('Unexpected Flickr client; cannot force authentication.'); // @codeCoverageIgnore
        }

        if ($inner instanceof ForceAuthenticatedFlickrClient) {
            return $flickr;
        }

        $property->setValue($raw, new ForceAuthenticatedFlickrClient($inner));

        return $flickr;
    }
}
