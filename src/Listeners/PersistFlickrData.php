<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Listeners;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use JOOservices\LaravelFlickr\Adapters\AbstractFlickrAdapter;
use JOOservices\LaravelFlickr\Adapters\Contacts;
use JOOservices\LaravelFlickr\Adapters\Favorites;
use JOOservices\LaravelFlickr\Adapters\Galleries;
use JOOservices\LaravelFlickr\Adapters\People;
use JOOservices\LaravelFlickr\Adapters\Photos;
use JOOservices\LaravelFlickr\Adapters\Photosets;
use JOOservices\LaravelFlickr\Adapters\Test;
use JOOservices\LaravelFlickr\Contracts\PersistsResults;
use JOOservices\LaravelFlickr\Events\FlickrCallCompleted;

/**
 * Resolves adapters by namespace without re-validating tokens (call already completed).
 */
final class PersistFlickrData
{
    public function __construct(private readonly Container $container) {}

    public function handle(FlickrCallCompleted $event): void
    {
        $adapter = $this->resolveAdapter($event->namespace, $event->appName, $event->nsid);
        if ($adapter instanceof PersistsResults) {
            $adapter->persist($event);
        }
    }

    private function resolveAdapter(string $namespace, string $appName, ?string $nsid): AbstractFlickrAdapter
    {
        $class = match ($namespace) {
            'photos' => Photos::class,
            'people' => People::class,
            'contacts' => Contacts::class,
            'photosets' => Photosets::class,
            'galleries' => Galleries::class,
            'favorites' => Favorites::class,
            'test' => Test::class,
            default => throw new InvalidArgumentException("Unknown Flickr adapter namespace [{$namespace}]."),
        };

        $adapter = $this->container->make($class, [
            'appName' => $appName,
            'nsid' => $nsid,
        ]);

        if (! $adapter instanceof AbstractFlickrAdapter) {
            throw new InvalidArgumentException("Unknown Flickr adapter namespace [{$namespace}].");
        }

        return $adapter;
    }
}
