<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\Flickr\DTO\Common\RequestOptionsData;
use JOOservices\Flickr\Enums\CachePolicy;
use JOOservices\LaravelFlickr\Contracts\FlickrClientFactoryInterface;
use JOOservices\LaravelFlickr\Contracts\RuntimeSettingsResolverInterface;
use JOOservices\LaravelFlickr\Dto\FlickrCallOutcome;
use JOOservices\LaravelFlickr\Events\FlickrCallCompleted;
use JOOservices\LaravelFlickr\Events\FlickrCallFailed;
use JOOservices\LaravelFlickr\Events\FlickrCallStarting;
use JOOservices\LaravelFlickr\Exceptions\AppNotFoundException;
use JOOservices\LaravelFlickr\Exceptions\RateLimitedException;
use JOOservices\LaravelFlickr\Exceptions\TokenNotFoundException;
use JOOservices\LaravelFlickr\Jobs\Middleware\FlickrRateLimitMiddleware;
use JOOservices\LaravelFlickr\RateLimit\RequestLimiterInterface;
use JOOservices\LaravelFlickr\Repositories\AppRepository;
use JOOservices\LaravelFlickr\Repositories\TokenRepository;
use JOOservices\LaravelFlickr\Support\RateLimitConnectionKey;
use Throwable;

final class FlickrRequestJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $uniqueFor = 60;

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
    ) {
        /** @var RuntimeSettingsResolverInterface $runtime */
        $runtime = app(RuntimeSettingsResolverInterface::class);

        $connection = $runtime->queueConnection();
        if ($connection !== null) {
            $this->onConnection($connection);
        }

        $this->onQueue($runtime->queueName());
    }

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

    public function handle(
        FlickrClientFactoryInterface $clients,
        TokenRepository $tokens,
        AppRepository $apps,
        RequestLimiterInterface $limiter,
    ): ApiResponseData {
        event(new FlickrCallStarting(
            $this->namespace,
            $this->method,
            $this->appName,
            $this->nsid,
            $this->params,
            $this->queued,
        ));

        $startedAt = microtime(true);
        $app = $apps->find($this->appName) ?? throw new AppNotFoundException($this->appName);
        $connectionKey = RateLimitConnectionKey::fromApiKey($app->apiKey);

        try {
            if ($this->nsid !== null) {
                $token = $tokens->find($this->appName, $this->nsid)
                    ?? throw new TokenNotFoundException($this->nsid, $this->appName);
                $client = $clients->authenticated($app->credentials(), $token);
            } else {
                $client = $clients->anonymous($app->credentials());
            }

            $response = $client->raw()->call(
                "flickr.{$this->namespace}.{$this->method}",
                $this->params,
                new RequestOptionsData(
                    authenticated: $this->nsid !== null,
                    cache: $this->bypassCache ? CachePolicy::Disabled : CachePolicy::Default,
                ),
            );
        } catch (RateLimitedException|AppNotFoundException|TokenNotFoundException $e) {
            // Operational/config misses: rethrow without a redundant CallFailed audit row.
            throw $e;
        } catch (Throwable $e) {
            event(new FlickrCallFailed(
                $this->namespace,
                $this->method,
                $this->appName,
                $this->nsid,
                $this->params,
                $this->queued,
                $e::class,
                $e->getMessage(),
            ));

            throw $e;
        }

        $durationMs = (microtime(true) - $startedAt) * 1000;
        // Prefer acquire() snapshot — avoids 5 Redis round-trips per completed call.
        $status = $limiter->status($connectionKey, fresh: false);

        event(new FlickrCallCompleted(
            $this->namespace,
            $this->method,
            $this->appName,
            $this->nsid,
            $this->params,
            $this->queued,
            new FlickrCallOutcome(
                $response->ok,
                $this->countItems($response),
                $durationMs,
                $status->remaining,
                $status->limit,
                $response,
            ),
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
