<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\OAuth;

use InvalidArgumentException;
use JOOservices\Flickr\Config\FlickrConfig;
use JOOservices\Flickr\Contracts\Client\FlickrTransportContract;
use JOOservices\Flickr\Enums\AuthPermission;
use JOOservices\Flickr\Flickr;
use JOOservices\Flickr\FlickrFactory;
use Jooservices\LaravelFlickr\Dto\AppCredentials;
use Jooservices\LaravelFlickr\Dto\OAuthBeginResult;
use Jooservices\LaravelFlickr\Dto\OAuthToken;

final class OAuthService
{
    public function __construct(
        private readonly ?FlickrTransportContract $transport = null,
    ) {}

    public function begin(
        AppCredentials $credentials,
        AuthPermission $permission = AuthPermission::Read,
    ): OAuthBeginResult {
        $client = $this->client($credentials);
        $requestToken = $client->auth()->requestToken($permission);

        return new OAuthBeginResult(
            $client->auth()->authorizationUrl($requestToken, $permission),
            $requestToken->oauthToken,
            $requestToken->oauthTokenSecret,
        );
    }

    public function complete(
        AppCredentials $credentials,
        string $oauthToken,
        string $oauthVerifier,
        string $oauthTokenSecret,
    ): OAuthToken {
        $accessToken = $this->client($credentials)
            ->auth()
            ->accessToken($oauthToken, $oauthVerifier, $oauthTokenSecret);
        if (! is_string($accessToken->userNsid) || trim($accessToken->userNsid) === '') {
            throw new InvalidArgumentException('Flickr access token did not include a user NSID.');
        }

        return new OAuthToken(
            $accessToken->oauthToken,
            $accessToken->oauthTokenSecret,
            $accessToken->userNsid,
            $accessToken->username,
            $accessToken->fullname,
        );
    }

    private function client(AppCredentials $credentials): Flickr
    {
        return FlickrFactory::make(
            FlickrConfig::from(['apiKey' => $credentials->apiKey, 'apiSecret' => $credentials->apiSecret]),
            transport: $this->transport,
        );
    }
}
