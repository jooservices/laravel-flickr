<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Http;

use Illuminate\Support\Facades\Event;
use JOOservices\Flickr\Client\FakeFlickrTransport;
use JOOservices\LaravelFlickr\Events\FlickrOAuthCompleted;
use JOOservices\LaravelFlickr\Models\Token;
use JOOservices\LaravelFlickr\OAuth\OAuthService;
use JOOservices\LaravelFlickr\OAuth\PendingAuthorizationStore;
use JOOservices\LaravelFlickr\Repositories\AppRepository;
use JOOservices\LaravelFlickr\Repositories\TokenRepository;
use JOOservices\LaravelFlickr\Tests\Support\FlickrNsid;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class OAuthCallbackControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->requiresRedis();
        $this->clearFlickrCollections();
        $this->storeApp();
    }

    #[Test]
    public function callback_completes_oauth_when_pending_exists(): void
    {
        Event::fake([FlickrOAuthCompleted::class]);

        $nsid = FlickrNsid::fake();
        $username = fake()->userName();
        $requestToken = fake()->sha1();
        $correlationId = fake()->uuid();

        app(PendingAuthorizationStore::class)->put(
            $requestToken,
            'request-secret',
            $this->defaultAppName,
            $correlationId,
            900,
        );

        $transport = FakeFlickrTransport::new()
            ->push('oauth_token=access-token&oauth_token_secret=access-secret&user_nsid='.rawurlencode($nsid).'&username='.rawurlencode($username));
        $this->app->instance(OAuthService::class, new OAuthService($transport, app(TokenRepository::class)));

        $response = $this->getJson('/api/v1/oauth/flickr/callback?oauth_token='.$requestToken.'&oauth_verifier=verifier');

        $response->assertOk();
        $response->assertJsonPath('data.nsid', $nsid);
        $response->assertJsonPath('data.correlation_id', $correlationId);
        $this->assertNotNull(
            Token::query()->where('app_name', $this->defaultAppName)->where('nsid', $nsid)->first(),
        );
        Event::assertDispatched(FlickrOAuthCompleted::class);
    }

    #[Test]
    public function callback_returns_404_when_pending_is_missing(): void
    {
        $response = $this->getJson('/api/v1/oauth/flickr/callback?oauth_token='.fake()->sha1().'&oauth_verifier=x');

        $response->assertNotFound();
    }

    #[Test]
    public function callback_validates_required_oauth_parameters(): void
    {
        $response = $this->getJson('/api/v1/oauth/flickr/callback');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['oauth_token', 'oauth_verifier']);
    }

    #[Test]
    public function callback_rejects_too_short_oauth_token(): void
    {
        $response = $this->getJson('/api/v1/oauth/flickr/callback?oauth_token=short&oauth_verifier=ok');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['oauth_token']);
    }

    #[Test]
    public function callback_returns_404_when_pending_app_was_deleted(): void
    {
        $this->storeApp('gone-app', 'k', 's');
        $requestToken = fake()->sha1();
        app(PendingAuthorizationStore::class)->put($requestToken, 'secret', 'gone-app', null, 900);
        app(AppRepository::class)->forget('gone-app');

        $response = $this->getJson('/api/v1/oauth/flickr/callback?oauth_token='.$requestToken.'&oauth_verifier=verifier-ok');

        $response->assertNotFound();
        $this->assertStringContainsString('gone-app', (string) $response->getContent());
    }
}
