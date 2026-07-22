<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\OAuth;

use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use JOOservices\Flickr\Client\FakeFlickrTransport;
use JOOservices\LaravelFlickr\Dto\AppCredentials;
use JOOservices\LaravelFlickr\Events\FlickrOAuthCompleted;
use JOOservices\LaravelFlickr\Models\Token;
use JOOservices\LaravelFlickr\OAuth\OAuthService;
use JOOservices\LaravelFlickr\Repositories\TokenRepository;
use JOOservices\LaravelFlickr\Tests\Support\FlickrNsid;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class OAuthServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->clearFlickrCollections();
        $this->storeApp();
    }

    #[Test]
    public function it_authorizes_and_completes_oauth_storing_the_token(): void
    {
        Event::fake([FlickrOAuthCompleted::class]);

        $nsid = FlickrNsid::fake();
        $username = fake()->userName();
        $transport = FakeFlickrTransport::new()
            ->push('oauth_token=request-token&oauth_token_secret=request-secret&oauth_callback_confirmed=true')
            ->push('oauth_token=access-token&oauth_token_secret=access-secret&user_nsid='.rawurlencode($nsid).'&username='.rawurlencode($username));

        $service = new OAuthService($transport, app(TokenRepository::class));
        $credentials = new AppCredentials('key', 'secret');

        $begin = $service->authorize($credentials, callbackUrl: 'https://app.test/flickr/callback');
        $token = $service->complete(
            $credentials,
            $this->defaultAppName,
            $begin->requestToken,
            'verifier',
            $begin->requestTokenSecret,
            'corr-1',
        );

        $this->assertStringContainsString('oauth_token=request-token', $begin->authorizationUrl);
        $request = $transport->sentRequests()[0];
        $this->assertSame('https://app.test/flickr/callback', $request['options']['query']['oauth_callback']);
        $this->assertSame('access-token', $token->oauthToken);
        $this->assertSame($nsid, $token->userNsid);

        $stored = Token::query()->where('app_name', $this->defaultAppName)->where('nsid', $nsid)->first();
        $this->assertNotNull($stored);
        $this->assertSame('access-token', $stored->oauth_token);

        Event::assertDispatched(FlickrOAuthCompleted::class, function (FlickrOAuthCompleted $event) use ($nsid): bool {
            return $event->nsid === $nsid
                && $event->appName === $this->defaultAppName
                && $event->correlationId === 'corr-1';
        });
    }

    #[Test]
    public function it_rejects_an_access_token_without_an_nsid(): void
    {
        $transport = FakeFlickrTransport::new()
            ->push('oauth_token=access-token&oauth_token_secret=access-secret');
        $service = new OAuthService($transport, app(TokenRepository::class));

        $this->expectException(InvalidArgumentException::class);
        $service->complete(
            new AppCredentials('key', 'secret'),
            $this->defaultAppName,
            'request-token',
            'verifier',
            'request-secret',
        );
    }

    #[Test]
    public function rejected_complete_does_not_store_a_token(): void
    {
        $transport = FakeFlickrTransport::new()
            ->push('oauth_token=access-token&oauth_token_secret=access-secret');
        $service = new OAuthService($transport, app(TokenRepository::class));
        $before = Token::query()->count();

        try {
            $service->complete(
                new AppCredentials('key', 'secret'),
                $this->defaultAppName,
                'request-token',
                'verifier',
                'request-secret',
            );
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException) {
            $this->assertSame($before, Token::query()->count());
        }
    }
}
