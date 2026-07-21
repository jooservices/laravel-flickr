<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Tests\Unit\OAuth;

use InvalidArgumentException;
use JOOservices\Flickr\Client\FakeFlickrTransport;
use Jooservices\LaravelFlickr\Dto\AppCredentials;
use Jooservices\LaravelFlickr\OAuth\OAuthService;
use Jooservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class OAuthServiceTest extends TestCase
{
    #[Test]
    public function it_begins_and_completes_oauth_without_host_state(): void
    {
        $transport = FakeFlickrTransport::new()
            ->push('oauth_token=request-token&oauth_token_secret=request-secret&oauth_callback_confirmed=true')
            ->push('oauth_token=access-token&oauth_token_secret=access-secret&user_nsid=1%40N01&username=user');
        $service = new OAuthService($transport);
        $credentials = new AppCredentials('key', 'secret');

        $begin = $service->begin($credentials, callbackUrl: 'https://app.test/flickr/callback');
        $token = $service->complete($credentials, $begin->requestToken, 'verifier', $begin->requestTokenSecret);

        $this->assertStringContainsString('oauth_token=request-token', $begin->authorizationUrl);
        $request = $transport->sentRequests()[0];

        $this->assertSame('https://app.test/flickr/callback', $request['options']['query']['oauth_callback']);
        $this->assertSame('access-token', $token->oauthToken);
        $this->assertSame('1@N01', $token->userNsid);
    }

    #[Test]
    public function it_rejects_an_access_token_without_an_nsid(): void
    {
        $transport = FakeFlickrTransport::new()
            ->push('oauth_token=access-token&oauth_token_secret=access-secret');
        $service = new OAuthService($transport);
        $this->expectException(InvalidArgumentException::class);
        $service->complete(new AppCredentials('key', 'secret'), 'request-token', 'verifier', 'request-secret');
    }
}
