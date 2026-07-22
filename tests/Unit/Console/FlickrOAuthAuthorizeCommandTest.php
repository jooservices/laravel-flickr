<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Console;

use JOOservices\LaravelFlickr\Dto\OAuthBeginResult;
use JOOservices\LaravelFlickr\Exceptions\AppNotFoundException;
use JOOservices\LaravelFlickr\OAuth\OAuthService;
use JOOservices\LaravelFlickr\OAuth\PendingAuthorizationStore;
use JOOservices\LaravelFlickr\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

final class FlickrOAuthAuthorizeCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->requiresRedis();
        $this->clearFlickrCollections();
    }

    #[Test]
    public function it_fails_when_app_is_not_registered(): void
    {
        $this->expectException(AppNotFoundException::class);

        $this->artisan('flickr:oauth:authorize', ['connection' => 'missing']);
    }

    #[Test]
    public function it_starts_oob_authorization_and_stores_pending_state(): void
    {
        $this->storeApp();

        $oauth = Mockery::mock(OAuthService::class);
        $oauth->shouldReceive('authorize')
            ->once()
            ->andReturn(new OAuthBeginResult(
                'https://flickr.test/auth?oauth_token=request-token',
                'request-token',
                'request-secret',
            ));
        $this->app->instance(OAuthService::class, $oauth);

        $this->artisan('flickr:oauth:authorize')
            ->expectsOutputToContain('Authorization URL: https://flickr.test/auth?oauth_token=request-token')
            ->expectsOutputToContain('oauth_token: request-token')
            ->expectsOutputToContain('connection: default')
            ->expectsOutputToContain('flickr:oauth:complete')
            ->assertSuccessful();

        $pending = app(PendingAuthorizationStore::class)->consume('request-token');
        $this->assertNotNull($pending);
        $this->assertSame('request-secret', $pending->oauthTokenSecret);
        $this->assertSame($this->defaultAppName, $pending->appName);
    }

    #[Test]
    public function it_starts_web_callback_authorization(): void
    {
        $this->storeApp('secondary', 'key-2', 'secret-2');

        $oauth = Mockery::mock(OAuthService::class);
        $oauth->shouldReceive('authorize')
            ->once()
            ->withArgs(fn ($credentials, $permission, ?string $callbackUrl): bool => $credentials->apiKey === 'key-2'
                && $callbackUrl === 'https://app.test/flickr/callback')
            ->andReturn(new OAuthBeginResult(
                'https://flickr.test/auth?oauth_token=web-token',
                'web-token',
                'web-secret',
            ));
        $this->app->instance(OAuthService::class, $oauth);

        $this->artisan('flickr:oauth:authorize', [
            'connection' => 'secondary',
            '--callback-url' => 'https://app.test/flickr/callback',
            '--correlation-id' => 'corr-9',
        ])
            ->expectsOutputToContain('Authorization URL: https://flickr.test/auth?oauth_token=web-token')
            ->expectsOutputToContain('redirect back')
            ->assertSuccessful();
    }
}
