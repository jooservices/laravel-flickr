<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Support;

use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\LaravelFlickr\Contracts\RuntimeSettingsResolverInterface;
use JOOservices\LaravelFlickr\Jobs\FlickrRequestJob;
use JOOservices\LaravelFlickr\Jobs\Middleware\FlickrRateLimitMiddleware;

/**
 * Shared sync/queue dispatch for adapters and FlickrService::call().
 */
final class FlickrJobDispatcher
{
    /**
     * @param  array<string, mixed>  $params
     */
    public static function dispatch(
        string $namespace,
        string $method,
        string $appName,
        ?string $nsid,
        array $params,
        bool $queued,
        bool $bypassCache,
        bool $applyDefaultPerPage = false,
    ): ?ApiResponseData {
        if ($applyDefaultPerPage && ! array_key_exists('per_page', $params)) {
            $params['per_page'] = app(RuntimeSettingsResolverInterface::class)->defaultPerPage();
        }

        $job = new FlickrRequestJob($namespace, $method, $appName, $nsid, $params, $bypassCache, $queued);

        if ($queued) {
            dispatch($job);

            return null;
        }

        $result = (new FlickrRateLimitMiddleware())->handle(
            $job,
            static fn (FlickrRequestJob $pending): mixed => app()->call([$pending, 'handle']),
        );

        return $result instanceof ApiResponseData ? $result : null;
    }
}
