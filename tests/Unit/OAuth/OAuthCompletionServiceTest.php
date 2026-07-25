<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\OAuth;

use JOOservices\LaravelFlickr\Dto\OAuthToken;
use JOOservices\LaravelFlickr\OAuth\OAuthCompletionService;
use JOOservices\LaravelFlickr\OAuth\OAuthService;
use JOOservices\LaravelFlickr\OAuth\PendingAuthorizationStore;
use JOOservices\LaravelFlickr\Tests\Support\FlickrNsid;
use JOOservices\LaravelFlickr\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

final class OAuthCompletionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->requiresRedis();
        $this->clearFlickrCollections();
    }

    #[Test]
    public function complete_pending_returns_404_when_missing(): void
    {
        $result = app(OAuthCompletionService::class)->completePending('missing', 'v');
        $this->assertFalse($result->ok);
        $this->assertSame(404, $result->status);
    }

    #[Test]
    public function complete_pending_returns_token_when_valid(): void
    {
        $this->storeApp();
        $nsid = FlickrNsid::fake();
        app(PendingAuthorizationStore::class)->put('tok', 'sec', $this->defaultAppName, 'c1', 900);

        $oauth = Mockery::mock(OAuthService::class);
        $oauth->shouldReceive('complete')->once()->andReturn(new OAuthToken(
            'a',
            'b',
            $nsid,
            'user',
            'Full',
        ));
        $this->app->instance(OAuthService::class, $oauth);

        $result = app(OAuthCompletionService::class)->completePending('tok', 'ver');
        $this->assertTrue($result->ok);
        $this->assertSame($nsid, $result->token?->userNsid);
        $this->assertSame('c1', $result->correlationId);
    }
}
