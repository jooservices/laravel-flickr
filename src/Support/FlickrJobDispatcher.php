<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Support;

use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\LaravelFlickr\Contracts\RuntimeSettingsResolverInterface;
use JOOservices\LaravelFlickr\Jobs\FlickrRequestJob;
use JOOservices\LaravelFlickr\Jobs\Middleware\FlickrRateLimitMiddleware;
use JOOservices\LaravelFlickr\Jobs\UniqueFlickrRequestJob;

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
        bool $unique = false,
        ?string $correlationId = null,
    ): ?ApiResponseData {
        $runtime = app(RuntimeSettingsResolverInterface::class);

        if ($applyDefaultPerPage && ! array_key_exists('per_page', $params)) {
            $params['per_page'] = $runtime->defaultPerPage();
        }

        $job = $unique
            ? new UniqueFlickrRequestJob($namespace, $method, $appName, $nsid, $params, $bypassCache, $queued, $correlationId)
            : new FlickrRequestJob($namespace, $method, $appName, $nsid, $params, $bypassCache, $queued, $correlationId);

        $connection = $runtime->queueConnection();
        if ($connection !== null) {
            $job->onConnection($connection);
        }
        $job->onQueue($runtime->queueName());

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
