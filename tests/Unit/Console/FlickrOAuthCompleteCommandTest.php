<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Console;

use JOOservices\LaravelFlickr\Dto\OAuthToken;
use JOOservices\LaravelFlickr\Exceptions\AppNotFoundException;
use JOOservices\LaravelFlickr\OAuth\OAuthService;
use JOOservices\LaravelFlickr\OAuth\PendingAuthorizationStore;
use JOOservices\LaravelFlickr\Tests\Support\FlickrNsid;
use JOOservices\LaravelFlickr\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

final class FlickrOAuthCompleteCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->requiresRedis();
        $this->clearFlickrCollections();
    }

    #[Test]
    public function it_requires_token_and_verifier(): void
    {
        $this->artisan('flickr:oauth:complete')
            ->expectsOutputToContain('Both --oauth-token and --verifier are required.')
            ->assertFailed();
    }

    #[Test]
    public function it_fails_when_pending_authorization_is_missing(): void
    {
        $this->artisan('flickr:oauth:complete', [
            '--oauth-token' => 'missing-token',
            '--verifier' => 'verifier',
        ])
            ->expectsOutputToContain('Authorization request not found or expired.')
            ->assertFailed();
    }

    #[Test]
    public function it_fails_when_pending_app_was_removed(): void
    {
        app(PendingAuthorizationStore::class)->put(
            'request-token',
            'request-secret',
            'gone-app',
            null,
            900,
        );

        $this->expectException(AppNotFoundException::class);

        $this->artisan('flickr:oauth:complete', [
            '--oauth-token' => 'request-token',
            '--verifier' => 'verifier',
        ]);
    }

    #[Test]
    public function it_completes_oauth_and_prints_account_details(): void
    {
        $this->storeApp();
        $nsid = FlickrNsid::fake();
        $username = fake()->userName();

        app(PendingAuthorizationStore::class)->put(
            'request-token',
            'request-secret',
            $this->defaultAppName,
            'corr-1',
            900,
        );

        $oauth = Mockery::mock(OAuthService::class);
        $oauth->shouldReceive('complete')
            ->once()
            ->withArgs(function (
                mixed $credentials,
                string $appName,
                string $oauthToken,
                string $verifier,
                string $secret,
                ?string $correlationId,
            ): bool {
                return $appName === $this->defaultAppName
                    && $oauthToken === 'request-token'
                    && $verifier === 'verifier'
                    && $secret === 'request-secret'
                    && $correlationId === 'corr-1';
            })
            ->andReturn(new OAuthToken(
                oauthToken: 'access-token',
                oauthTokenSecret: 'access-secret',
                userNsid: $nsid,
                username: $username,
                fullname: 'Full Name',
            ));
        $this->app->instance(OAuthService::class, $oauth);

        $this->artisan('flickr:oauth:complete', [
            '--oauth-token' => 'request-token',
            '--verifier' => 'verifier',
        ])
            ->expectsOutputToContain("Connected Flickr account: {$username} ({$nsid}) on [default]")
            ->expectsOutputToContain("FlickrService::connection('default')->as('{$nsid}')")
            ->assertSuccessful();
    }
}
