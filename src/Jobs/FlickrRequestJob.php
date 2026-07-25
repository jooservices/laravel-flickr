<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\LaravelFlickr\Jobs\Middleware\FlickrRateLimitMiddleware;
use JOOservices\LaravelFlickr\Service\FlickrCallService;
use JOOservices\LaravelFlickr\Support\FlickrJobDispatcher;

/**
 * Thin queue/sync shell — business logic lives in {@see FlickrCallService}.
 * Not unique by default (see {@see UniqueFlickrRequestJob} for opt-in dedupe).
 *
 * Constructor is a pure data bag; queue connection/name are applied by
 * {@see FlickrJobDispatcher}.
 *
 * Not final so {@see UniqueFlickrRequestJob} can opt into ShouldBeUnique.
 */
class FlickrRequestJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public readonly string $namespace,
        public readonly string $method,
        public readonly string $appName,
        public readonly ?string $nsid,
        public readonly array $params,
        public readonly bool $bypassCache = false,
        public readonly bool $queued = false,
        public readonly ?string $correlationId = null,
    ) {}

    public function uniqueId(): string
    {
        return sprintf(
            'flickr.%s.%s:%s:%s:%s',
            $this->namespace,
            $this->method,
            $this->appName,
            $this->nsid ?? 'anonymous',
            hash('xxh128', json_encode($this->params, JSON_THROW_ON_ERROR)),
        );
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new FlickrRateLimitMiddleware()];
    }

    public function handle(FlickrCallService $calls): ApiResponseData
    {
        return $calls->execute(
            $this->namespace,
            $this->method,
            $this->appName,
            $this->nsid,
            $this->params,
            $this->queued,
            $this->bypassCache,
            $this->correlationId,
        );
    }
}
