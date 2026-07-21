<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Client;

use JOOservices\Flickr\Auth\InMemoryTokenStore;
use JOOservices\Flickr\Config\FlickrConfig;
use JOOservices\Flickr\Contracts\Client\FlickrTransportContract;
use JOOservices\Flickr\Flickr;
use JOOservices\Flickr\FlickrFactory;
use Jooservices\LaravelFlickr\Contracts\AppCredentialsResolverInterface;
use Jooservices\LaravelFlickr\Contracts\FlickrClientFactoryInterface;
use Jooservices\LaravelFlickr\Dto\AppCredentials;
use Jooservices\LaravelFlickr\Dto\OAuthToken;

/**
 * Sole owner of FlickrFactory::make for this package.
 */
final class FlickrClientFactory implements FlickrClientFactoryInterface
{
    public function __construct(
        private readonly AppCredentialsResolverInterface $credentials,
    ) {}

    public function authenticated(
        AppCredentials $credentials,
        OAuthToken $token,
        ?FlickrTransportContract $transport = null,
    ): Flickr {
        $config = FlickrConfig::from([
            'apiKey' => $credentials->apiKey,
            'apiSecret' => $credentials->apiSecret,
        ]);

        $client = FlickrFactory::make(
            $config,
            tokenStore: new InMemoryTokenStore($token->toAccessTokenData()),
            transport: $transport,
        );

        return ForceAuthenticatedFlickr::wrap($client);
    }

    public function anonymous(
        AppCredentials $credentials,
        ?FlickrTransportContract $transport = null,
    ): Flickr {
        $config = FlickrConfig::from([
            'apiKey' => $credentials->apiKey,
            'apiSecret' => $credentials->apiSecret,
        ]);

        return FlickrFactory::make($config, transport: $transport);
    }

    public function authenticatedFromConfig(
        OAuthToken $token,
        ?FlickrTransportContract $transport = null,
    ): Flickr {
        return $this->authenticated($this->credentials->resolve(), $token, $transport);
    }

    public function anonymousFromConfig(?FlickrTransportContract $transport = null): Flickr
    {
        return $this->anonymous($this->credentials->resolve(), $transport);
    }
}
