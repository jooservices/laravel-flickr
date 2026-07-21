<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Contracts;

use JOOservices\Flickr\Contracts\Client\FlickrTransportContract;
use JOOservices\Flickr\Flickr;
use Jooservices\LaravelFlickr\Dto\AppCredentials;
use Jooservices\LaravelFlickr\Dto\OAuthToken;

/**
 * Sole construction site for SDK clients used by this package.
 *
 * Host apps should not call FlickrFactory::make for account-bound work when using this package.
 */
interface FlickrClientFactoryInterface
{
    /**
     * Account-bound client: OAuth-signed REST by default (force-auth wrap).
     */
    public function authenticated(
        AppCredentials $credentials,
        OAuthToken $token,
        ?FlickrTransportContract $transport = null,
    ): Flickr;

    /**
     * App-key only client (no user token). For intentional anonymous/public probes.
     */
    public function anonymous(
        AppCredentials $credentials,
        ?FlickrTransportContract $transport = null,
    ): Flickr;

    /**
     * Authenticated client using credentials from the configured resolver.
     */
    public function authenticatedFromConfig(
        OAuthToken $token,
        ?FlickrTransportContract $transport = null,
    ): Flickr;

    /**
     * Anonymous client using credentials from the configured resolver.
     */
    public function anonymousFromConfig(?FlickrTransportContract $transport = null): Flickr;
}
