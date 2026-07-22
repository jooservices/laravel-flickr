<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\OAuth;

use InvalidArgumentException;
use JOOservices\Flickr\Config\FlickrConfig;
use JOOservices\Flickr\Contracts\Client\FlickrTransportContract;
use JOOservices\Flickr\Enums\AuthPermission;
use JOOservices\Flickr\Flickr;
use JOOservices\Flickr\FlickrFactory;
use JOOservices\LaravelFlickr\Dto\AppCredentials;
use JOOservices\LaravelFlickr\Dto\OAuthBeginResult;
use JOOservices\LaravelFlickr\Dto\OAuthToken;
use JOOservices\LaravelFlickr\Events\FlickrOAuthCompleted;
use JOOservices\LaravelFlickr\Repositories\TokenRepository;

final class OAuthService
{
    public function __construct(
        private readonly ?FlickrTransportContract $transport,
        private readonly TokenRepository $tokens,
    ) {}

    public function authorize(
        AppCredentials $credentials,
        AuthPermission $permission = AuthPermission::Read,
        ?string $callbackUrl = null,
    ): OAuthBeginResult {
        $client = $this->client($credentials, $callbackUrl);
        $requestToken = $client->auth()->requestToken($permission);

        return new OAuthBeginResult(
            $client->auth()->authorizationUrl($requestToken, $permission),
            $requestToken->oauthToken,
            $requestToken->oauthTokenSecret,
        );
    }

    public function complete(
        AppCredentials $credentials,
        string $appName,
        string $oauthToken,
        string $oauthVerifier,
        string $oauthTokenSecret,
        ?string $correlationId = null,
    ): OAuthToken {
        $accessToken = $this->client($credentials)->auth()->accessToken($oauthToken, $oauthVerifier, $oauthTokenSecret);

        $userNsid = $accessToken->userNsid;
        if (! is_string($userNsid) || trim($userNsid) === '') {
            throw new InvalidArgumentException('Flickr access token did not include a user NSID.');
        }

        $token = new OAuthToken(
            $accessToken->oauthToken,
            $accessToken->oauthTokenSecret,
            $userNsid,
            $accessToken->username,
            $accessToken->fullname,
        );

        $this->tokens->store($appName, $token);

        event(new FlickrOAuthCompleted($appName, $userNsid, $token->username, $token->fullname, $correlationId));

        return $token;
    }

    private function client(AppCredentials $credentials, ?string $callbackUrl = null): Flickr
    {
        return FlickrFactory::make(
            new FlickrConfig(
                apiKey: $credentials->apiKey,
                apiSecret: $credentials->apiSecret,
                callbackUrl: $callbackUrl,
                enableRateLimit: false,
            ),
            transport: $this->transport,
        );
    }
}
