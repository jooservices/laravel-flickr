<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Jobs\Middleware;

use Closure;
use JOOservices\LaravelFlickr\Events\FlickrRateLimited;
use JOOservices\LaravelFlickr\Exceptions\RateLimitedException;
use JOOservices\LaravelFlickr\Jobs\FlickrRequestJob;

/**
 * Single place that decides release()-vs-rethrow for rate-limit denials,
 * for both queued and sync dispatch modes.
 */
final class FlickrRateLimitMiddleware
{
    /**
     * @param  Closure(FlickrRequestJob): mixed  $next
     */
    public function handle(FlickrRequestJob $job, Closure $next): mixed
    {
        try {
            return $next($job);
        } catch (RateLimitedException $e) {
            event(new FlickrRateLimited(
                $job->appName,
                $job->namespace,
                $job->method,
                $job->nsid,
                $e->retryAfterSeconds,
                $e->denyReason,
            ));

            if ($job->queued) {
                $job->release($e->retryAfterSeconds);

                return null;
            }

            throw $e;
        }
    }
}
