<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Jobs;

use Illuminate\Support\Facades\Event;
use JOOservices\LaravelFlickr\Events\FlickrRateLimited;
use JOOservices\LaravelFlickr\Exceptions\RateLimitedException;
use JOOservices\LaravelFlickr\Jobs\FlickrRequestJob;
use JOOservices\LaravelFlickr\Jobs\Middleware\FlickrRateLimitMiddleware;
use JOOservices\LaravelFlickr\RateLimit\DenyReason;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class FlickrRateLimitMiddlewareTest extends TestCase
{
    #[Test]
    public function sync_denials_are_rethrown_after_event(): void
    {
        Event::fake([FlickrRateLimited::class]);

        $job = new FlickrRequestJob('contacts', 'getList', 'default', '1@N01', [], queued: false);
        $middleware = new FlickrRateLimitMiddleware();

        try {
            $middleware->handle($job, static function (): never {
                throw new RateLimitedException(15, DenyReason::MinGap, 'api-key');
            });
            $this->fail('Expected RateLimitedException');
        } catch (RateLimitedException $e) {
            $this->assertSame(15, $e->retryAfterSeconds);
        }

        Event::assertDispatched(FlickrRateLimited::class, function (FlickrRateLimited $event): bool {
            return $event->appName === 'default'
                && $event->namespace === 'contacts'
                && $event->method === 'getList'
                && $event->retryAfterSeconds === 15;
        });
    }

    #[Test]
    public function queued_denials_release_instead_of_throwing(): void
    {
        Event::fake([FlickrRateLimited::class]);

        $job = $this->getMockBuilder(FlickrRequestJob::class)
            ->setConstructorArgs(['contacts', 'getList', 'default', '1@N01', [], false, true])
            ->onlyMethods(['release'])
            ->getMock();
        $job->expects($this->once())->method('release')->with(33);

        $result = (new FlickrRateLimitMiddleware())->handle($job, static function (): never {
            throw new RateLimitedException(33, DenyReason::HourlyQuota, 'api-key');
        });

        $this->assertNull($result);
        Event::assertDispatched(FlickrRateLimited::class);
    }
}
