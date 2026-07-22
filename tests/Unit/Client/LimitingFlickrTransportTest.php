<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Client;

use Illuminate\Support\Facades\Event;
use JOOservices\Flickr\Contracts\Client\FlickrTransportContract;
use JOOservices\Flickr\DTO\Common\RawResponseData;
use JOOservices\LaravelFlickr\Client\LimitingFlickrTransport;
use JOOservices\LaravelFlickr\Contracts\RateLimitConfigResolverInterface;
use JOOservices\LaravelFlickr\Events\FlickrRateLimitApproaching;
use JOOservices\LaravelFlickr\Exceptions\RateLimitedException;
use JOOservices\LaravelFlickr\RateLimit\DenyReason;
use JOOservices\LaravelFlickr\RateLimit\NullRequestLimiter;
use JOOservices\LaravelFlickr\RateLimit\Permit;
use JOOservices\LaravelFlickr\Tests\Support\FixedPermitLimiter;
use JOOservices\LaravelFlickr\Tests\Support\RecordingCooldownLimiter;
use JOOservices\LaravelFlickr\Tests\Support\SequencePermitLimiter;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class LimitingFlickrTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
    }

    #[Test]
    public function deny_from_limiter_throws_before_http(): void
    {
        $inner = $this->createMock(FlickrTransportContract::class);
        $inner->expects($this->never())->method('request');

        $transport = new LimitingFlickrTransport(
            $inner,
            new FixedPermitLimiter(new Permit(false, 12, DenyReason::MinGap, remaining: 0, limit: 100)),
            'api-key',
            app(RateLimitConfigResolverInterface::class),
        );

        $this->expectException(RateLimitedException::class);
        $transport->request('GET', 'https://api.flickr.com/services/rest');
    }

    #[Test]
    public function http_429_triggers_cooldown_and_throws(): void
    {
        $inner = $this->createStub(FlickrTransportContract::class);
        $inner->method('request')->willReturn(new RawResponseData(
            statusCode: 429,
            headers: ['Retry-After' => ['45']],
            body: '',
        ));

        $limiter = new RecordingCooldownLimiter(new Permit(true, remaining: 50, limit: 100));

        $transport = new LimitingFlickrTransport(
            $inner,
            $limiter,
            'api-key',
            app(RateLimitConfigResolverInterface::class),
        );

        try {
            $transport->request('GET', 'https://api.flickr.com/services/rest');
            $this->fail('Expected RateLimitedException');
        } catch (RateLimitedException $e) {
            $this->assertSame(DenyReason::Cooldown, $e->denyReason);
            $this->assertSame(45, $e->retryAfterSeconds);
        }

        $this->assertSame(45, $limiter->lastCooldownSeconds);
    }

    #[Test]
    public function approaching_event_fires_only_on_threshold_transition(): void
    {
        $inner = $this->createStub(FlickrTransportContract::class);
        $inner->method('request')->willReturn(new RawResponseData(statusCode: 200, headers: [], body: '{}'));

        $limiter = new SequencePermitLimiter([
            new Permit(true, remaining: 90, limit: 100),
            new Permit(true, remaining: 15, limit: 100),
            new Permit(true, remaining: 10, limit: 100),
        ]);

        $transport = new LimitingFlickrTransport(
            $inner,
            $limiter,
            'api-key',
            app(RateLimitConfigResolverInterface::class),
        );

        $transport->request('GET', 'https://api.flickr.com/services/rest');
        Event::assertNotDispatched(FlickrRateLimitApproaching::class);

        $transport->request('GET', 'https://api.flickr.com/services/rest');
        Event::assertDispatchedTimes(FlickrRateLimitApproaching::class, 1);

        $transport->request('GET', 'https://api.flickr.com/services/rest');
        Event::assertDispatchedTimes(FlickrRateLimitApproaching::class, 1);
    }

    #[Test]
    public function successful_request_returns_inner_response(): void
    {
        $inner = $this->createStub(FlickrTransportContract::class);
        $expected = new RawResponseData(statusCode: 200, headers: [], body: '{"stat":"ok"}');
        $inner->method('request')->willReturn($expected);

        $transport = new LimitingFlickrTransport(
            $inner,
            new NullRequestLimiter(),
            'api-key',
            app(RateLimitConfigResolverInterface::class),
        );

        $this->assertSame($expected, $transport->request('GET', 'https://api.flickr.com/services/rest'));
    }

    #[Test]
    public function null_quota_skips_approaching_and_missing_retry_after_uses_cooldown_default(): void
    {
        $inner = $this->createStub(FlickrTransportContract::class);
        $inner->method('request')->willReturn(new RawResponseData(statusCode: 429, headers: [], body: ''));

        $transport = new LimitingFlickrTransport(
            $inner,
            new FixedPermitLimiter(new Permit(true, remaining: null, limit: null)),
            'ck',
            app(RateLimitConfigResolverInterface::class),
        );

        try {
            $transport->request('GET', 'https://api.flickr.com/services/rest');
            $this->fail('expected rate limit');
        } catch (RateLimitedException $e) {
            $this->assertSame(DenyReason::Cooldown, $e->denyReason);
            $this->assertGreaterThanOrEqual(1, $e->retryAfterSeconds);
        }
    }
}
