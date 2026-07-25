<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Service;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use JOOservices\Flickr\Flickr;
use JOOservices\LaravelFlickr\Adapters\AbstractFlickrAdapter;
use JOOservices\LaravelFlickr\Adapters\Contacts;
use JOOservices\LaravelFlickr\Adapters\Favorites;
use JOOservices\LaravelFlickr\Adapters\Galleries;
use JOOservices\LaravelFlickr\Adapters\People;
use JOOservices\LaravelFlickr\Adapters\Photos;
use JOOservices\LaravelFlickr\Adapters\Photosets;
use JOOservices\LaravelFlickr\Adapters\Tags;
use JOOservices\LaravelFlickr\Adapters\Test;
use JOOservices\LaravelFlickr\Contracts\FlickrClientFactoryInterface;
use JOOservices\LaravelFlickr\Contracts\RuntimeSettingsResolverInterface;
use JOOservices\LaravelFlickr\Dto\FlickrApp;
use JOOservices\LaravelFlickr\Events\FlickrClientResolved;
use JOOservices\LaravelFlickr\Exceptions\AppNotFoundException;
use JOOservices\LaravelFlickr\Exceptions\TokenNotFoundException;
use JOOservices\LaravelFlickr\RateLimit\RateLimitStatus;
use JOOservices\LaravelFlickr\RateLimit\RequestLimiterInterface;
use JOOservices\LaravelFlickr\Repositories\AppRepository;
use JOOservices\LaravelFlickr\Repositories\TokenRepository;
use JOOservices\LaravelFlickr\Support\FlickrAdapterRegistry;
use JOOservices\LaravelFlickr\Support\FlickrJobDispatcher;
use JOOservices\LaravelFlickr\Support\RateLimitConnectionKey;
use LogicException;

/**
 * @property-read Photos $photos
 * @property-read People $people
 * @property-read Contacts $contacts
 * @property-read Photosets $photosets
 * @property-read Galleries $galleries
 * @property-read Favorites $favorites
 * @property-read Test $test
 * @property-read Tags $tags
 */
final class FlickrService
{
    private ?string $connectionName = null;

    private ?string $nsid = null;

    private bool $scoped = false;

    public function __construct(
        private readonly Container $container,
        private readonly FlickrClientFactoryInterface $clients,
        private readonly TokenRepository $tokens,
        private readonly AppRepository $apps,
        private readonly RuntimeSettingsResolverInterface $runtimeSettings,
        private readonly RequestLimiterInterface $limiter,
    ) {}

    public function connection(string $name): self
    {
        $scoped = clone $this;
        $scoped->connectionName = $name;
        $scoped->nsid = null;
        $scoped->scoped = false;

        return $scoped;
    }

    public function as(string $nsid): self
    {
        $appName = $this->resolvedConnection();

        if (! $this->tokens->exists($appName, $nsid)) {
            throw new TokenNotFoundException($nsid, $appName);
        }

        $scoped = clone $this;
        $scoped->connectionName = $appName;
        $scoped->nsid = $nsid;
        $scoped->scoped = true;

        return $scoped;
    }

    public function anonymous(): self
    {
        $appName = $this->resolvedConnection();
        $this->requireApp($appName);

        $scoped = clone $this;
        $scoped->connectionName = $appName;
        $scoped->nsid = null;
        $scoped->scoped = true;

        return $scoped;
    }

    public function __get(string $name): AbstractFlickrAdapter
    {
        if (! $this->scoped) {
            throw new LogicException('Call ->as($nsid) or ->anonymous() before accessing an adapter.');
        }

        $class = FlickrAdapterRegistry::classFor($name);
        if ($class === null) {
            throw new InvalidArgumentException("Unknown Flickr adapter namespace [{$name}].");
        }

        $adapter = $this->container->make($class, [
            'nsid' => $this->nsid,
            'appName' => $this->resolvedConnection(),
        ]);

        if (! $adapter instanceof AbstractFlickrAdapter) {
            throw new InvalidArgumentException("Unknown Flickr adapter namespace [{$name}].");
        }

        return $adapter;
    }

    /**
     * Escape hatch for any Flickr method (including namespaces without a first-class adapter).
     *
     * @param  array<string, mixed>  $params
     */
    public function call(
        string $namespace,
        string $method,
        array $params = [],
        bool $queued = false,
        bool $bypassCache = false,
        bool $unique = false,
        ?string $correlationId = null,
    ): mixed {
        if (! $this->scoped) {
            throw new LogicException('Call ->as($nsid) or ->anonymous() before calling call().');
        }

        return FlickrJobDispatcher::dispatch(
            $namespace,
            $method,
            $this->resolvedConnection(),
            $this->nsid,
            $params,
            $queued,
            $bypassCache,
            unique: $unique,
            correlationId: $correlationId,
        );
    }

    public function getClient(): Flickr
    {
        if (! $this->scoped) {
            throw new LogicException('Call ->as($nsid) or ->anonymous() before calling getClient().');
        }

        $appName = $this->resolvedConnection();
        $app = $this->requireApp($appName);

        event(new FlickrClientResolved($appName, $this->nsid, authenticated: $this->nsid !== null));

        if ($this->nsid === null) {
            return $this->clients->anonymous($app->credentials());
        }

        $token = $this->tokens->find($appName, $this->nsid) ?? throw new TokenNotFoundException($this->nsid, $appName);

        return $this->clients->authenticated($app->credentials(), $token);
    }

    public function rateLimitStatus(): RateLimitStatus
    {
        $app = $this->requireApp($this->resolvedConnection());

        return $this->limiter->status(RateLimitConnectionKey::fromApiKey($app->apiKey));
    }

    private function resolvedConnection(): string
    {
        return $this->connectionName ?? $this->runtimeSettings->defaultConnection();
    }

    private function requireApp(string $appName): FlickrApp
    {
        return $this->apps->find($appName) ?? throw new AppNotFoundException($appName);
    }
}
