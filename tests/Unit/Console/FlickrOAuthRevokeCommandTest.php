<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Console;

use Illuminate\Support\Facades\Event;
use JOOservices\LaravelFlickr\Events\FlickrOAuthRevoked;
use JOOservices\LaravelFlickr\Models\Token;
use JOOservices\LaravelFlickr\Tests\Support\FlickrNsid;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class FlickrOAuthRevokeCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->clearFlickrCollections();
    }

    #[Test]
    public function it_rejects_an_empty_nsid(): void
    {
        $this->artisan('flickr:oauth:revoke', ['nsid' => ''])
            ->expectsOutputToContain('A non-empty NSID is required.')
            ->assertFailed();
    }

    #[Test]
    public function it_removes_the_stored_token_and_dispatches_revoked_event(): void
    {
        Event::fake([FlickrOAuthRevoked::class]);

        $token = $this->storeToken();
        $this->assertSame(1, Token::query()->count());

        $this->artisan('flickr:oauth:revoke', [
            'nsid' => $token->userNsid,
            '--connection' => $this->defaultAppName,
        ])
            ->expectsOutputToContain("Removed stored Flickr token for NSID [{$token->userNsid}]")
            ->assertSuccessful();

        $this->assertSame(0, Token::query()->count());
        Event::assertDispatched(FlickrOAuthRevoked::class, function (FlickrOAuthRevoked $event) use ($token): bool {
            return $event->nsid === $token->userNsid && $event->appName === $this->defaultAppName;
        });
    }

    #[Test]
    public function it_uses_the_default_connection_when_option_omitted(): void
    {
        Event::fake([FlickrOAuthRevoked::class]);
        $nsid = FlickrNsid::fake();
        $this->storeToken($nsid);

        $this->artisan('flickr:oauth:revoke', ['nsid' => $nsid])
            ->expectsOutputToContain("on connection [{$this->defaultAppName}]")
            ->assertSuccessful();
    }
}
