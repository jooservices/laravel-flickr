<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Service;

use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\Flickr\DTO\Common\RequestOptionsData;
use JOOservices\Flickr\Enums\CachePolicy;
use JOOservices\LaravelFlickr\Contracts\FlickrClientFactoryInterface;
use JOOservices\LaravelFlickr\Dto\FlickrCallOutcome;
use JOOservices\LaravelFlickr\Events\FlickrCallCompleted;
use JOOservices\LaravelFlickr\Events\FlickrCallFailed;
use JOOservices\LaravelFlickr\Events\FlickrCallStarting;
use JOOservices\LaravelFlickr\Exceptions\AppNotFoundException;
use JOOservices\LaravelFlickr\Exceptions\RateLimitedException;
use JOOservices\LaravelFlickr\Exceptions\TokenNotFoundException;
use JOOservices\LaravelFlickr\Jobs\FlickrRequestJob;
use JOOservices\LaravelFlickr\RateLimit\RequestLimiterInterface;
use JOOservices\LaravelFlickr\Repositories\AppRepository;
use JOOservices\LaravelFlickr\Repositories\TokenRepository;
use JOOservices\LaravelFlickr\Support\RateLimitConnectionKey;
use Throwable;

/**
 * Executes a single Flickr REST call and emits lifecycle events.
 * Used by {@see FlickrRequestJob} (sync and queued).
 */
final class FlickrCallService
{
    public function __construct(
        private readonly FlickrClientFactoryInterface $clients,
        private readonly TokenRepository $tokens,
        private readonly AppRepository $apps,
        private readonly RequestLimiterInterface $limiter,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(
        string $namespace,
        string $method,
        string $appName,
        ?string $nsid,
        array $params,
        bool $queued = false,
        bool $bypassCache = false,
        ?string $correlationId = null,
    ): ApiResponseData {
        event(new FlickrCallStarting(
            $namespace,
            $method,
            $appName,
            $nsid,
            $params,
            $queued,
            $correlationId,
        ));

        $startedAt = microtime(true);
        $app = $this->apps->find($appName) ?? throw new AppNotFoundException($appName);
        $connectionKey = RateLimitConnectionKey::fromApiKey($app->apiKey);

        try {
            if ($nsid !== null) {
                $token = $this->tokens->find($appName, $nsid)
                    ?? throw new TokenNotFoundException($nsid, $appName);
                $client = $this->clients->authenticated($app->credentials(), $token);
            } else {
                $client = $this->clients->anonymous($app->credentials());
            }

            $response = $client->raw()->call(
                "flickr.{$namespace}.{$method}",
                $params,
                new RequestOptionsData(
                    authenticated: $nsid !== null,
                    cache: $bypassCache ? CachePolicy::Disabled : CachePolicy::Default,
                ),
            );
        } catch (RateLimitedException|AppNotFoundException|TokenNotFoundException $e) {
            // Operational/config misses: rethrow without a redundant CallFailed audit row.
            throw $e;
        } catch (Throwable $e) {
            event(new FlickrCallFailed(
                $namespace,
                $method,
                $appName,
                $nsid,
                $params,
                $queued,
                $e::class,
                $e->getMessage(),
                $correlationId,
            ));

            throw $e;
        }

        $durationMs = (microtime(true) - $startedAt) * 1000;
        // Prefer acquire() snapshot — avoids 5 Redis round-trips per completed call.
        $status = $this->limiter->status($connectionKey, fresh: false);

        event(new FlickrCallCompleted(
            $namespace,
            $method,
            $appName,
            $nsid,
            $params,
            $queued,
            new FlickrCallOutcome(
                $response->ok,
                $this->countItems($response),
                $durationMs,
                $status->remaining,
                $status->limit,
                $response,
            ),
            $correlationId,
        ));

        return $response;
    }

    private function countItems(ApiResponseData $response): int
    {
        foreach ($response->data as $envelope) {
            if (is_array($envelope)) {
                foreach ($envelope as $value) {
                    if (is_array($value) && array_is_list($value)) {
                        return count($value);
                    }
                }
            }
        }

        return 0;
    }
}
