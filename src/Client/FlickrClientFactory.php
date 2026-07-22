<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Client;

use Illuminate\Support\Facades\Cache;
use JOOservices\Flickr\Auth\InMemoryTokenStore;
use JOOservices\Flickr\Cache\Psr16Cache;
use JOOservices\Flickr\Client\JooClientTransport;
use JOOservices\Flickr\Config\FlickrConfig;
use JOOservices\Flickr\Contracts\Cache\FlickrCacheContract;
use JOOservices\Flickr\Contracts\Client\FlickrTransportContract;
use JOOservices\Flickr\Flickr;
use JOOservices\Flickr\FlickrFactory;
use JOOservices\LaravelFlickr\Contracts\FlickrClientFactoryInterface;
use JOOservices\LaravelFlickr\Contracts\RateLimitConfigResolverInterface;
use JOOservices\LaravelFlickr\Contracts\RuntimeSettingsResolverInterface;
use JOOservices\LaravelFlickr\Dto\AppCredentials;
use JOOservices\LaravelFlickr\Dto\OAuthToken;
use JOOservices\LaravelFlickr\RateLimit\RequestLimiterInterface;
use JOOservices\LaravelFlickr\Support\RateLimitConnectionKey;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;

/**
 * Sole owner of FlickrFactory::make for this package.
 *
 * Always wraps the transport with {@see LimitingFlickrTransport} so every outbound
 * Flickr HTTP call goes through {@see RequestLimiterInterface::acquire()}.
 * SDK token-bucket rate limiting is disabled — this package never sleeps for limits.
 */
final class FlickrClientFactory implements FlickrClientFactoryInterface
{
    public function __construct(
        private readonly RequestLimiterInterface $limiter,
        private readonly RateLimitConfigResolverInterface $rateLimitConfig,
        private readonly RuntimeSettingsResolverInterface $runtimeSettings,
    ) {}

    public function authenticated(
        AppCredentials $credentials,
        OAuthToken $token,
        ?FlickrTransportContract $transport = null,
    ): Flickr {
        $config = $this->flickrConfig($credentials);

        return ForceAuthenticatedFlickr::wrap(FlickrFactory::make(
            $config,
            tokenStore: new InMemoryTokenStore($token->toAccessTokenData()),
            transport: $this->limitingTransport($config, RateLimitConnectionKey::fromApiKey($credentials->apiKey), $transport),
            cache: $this->cache(),
        ));
    }

    public function anonymous(
        AppCredentials $credentials,
        ?FlickrTransportContract $transport = null,
    ): Flickr {
        $config = $this->flickrConfig($credentials);

        return FlickrFactory::make(
            $config,
            transport: $this->limitingTransport($config, RateLimitConnectionKey::fromApiKey($credentials->apiKey), $transport),
            cache: $this->cache(),
        );
    }

    private function flickrConfig(AppCredentials $credentials): FlickrConfig
    {
        return FlickrConfig::from([
            'apiKey' => $credentials->apiKey,
            'apiSecret' => $credentials->apiSecret,
            // Package owns rate limits via LimitingFlickrTransport — never sleep in the SDK.
            'enableRateLimit' => false,
            'publicCacheTtlSeconds' => max(1, $this->runtimeSettings->cacheTtlSeconds()),
        ]);
    }

    private function cache(): FlickrCacheContract
    {
        $store = $this->runtimeSettings->cacheStore();
        $repository = $store !== null ? Cache::store($store) : Cache::store();

        if (! $repository instanceof CacheInterface) {
            throw new RuntimeException('Configured Laravel cache store must implement PSR-16 CacheInterface.');
        }

        return new Psr16Cache($repository);
    }

    private function limitingTransport(
        FlickrConfig $config,
        string $connectionKey,
        ?FlickrTransportContract $transport,
    ): LimitingFlickrTransport {
        $inner = $transport ?? JooClientTransport::fromConfig($config);

        return new LimitingFlickrTransport($inner, $this->limiter, $connectionKey, $this->rateLimitConfig);
    }
}
