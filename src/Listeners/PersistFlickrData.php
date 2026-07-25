<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Listeners;

use Illuminate\Contracts\Container\Container;
use JOOservices\LaravelFlickr\Adapters\AbstractFlickrAdapter;
use JOOservices\LaravelFlickr\Contracts\PersistsResults;
use JOOservices\LaravelFlickr\Events\FlickrCallCompleted;
use JOOservices\LaravelFlickr\Service\FlickrService;
use JOOservices\LaravelFlickr\Support\FlickrAdapterRegistry;

/**
 * Resolves adapters by namespace without re-validating tokens (call already completed).
 * Unknown namespaces no-op so {@see FlickrService::call()}
 * escape-hatch methods never fail after a successful Flickr HTTP response.
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

    private function resolveAdapter(string $namespace, string $appName, ?string $nsid): ?AbstractFlickrAdapter
    {
        $class = FlickrAdapterRegistry::classFor($namespace);
        if ($class === null) {
            return null;
        }

        $adapter = $this->container->make($class, [
            'appName' => $appName,
            'nsid' => $nsid,
        ]);

        return $adapter instanceof AbstractFlickrAdapter ? $adapter : null;
    }
}
