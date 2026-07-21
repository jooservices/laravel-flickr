<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Client;

use JOOservices\Flickr\Contracts\Client\FlickrTransportContract;
use JOOservices\Flickr\DTO\Common\RawResponseData;
use Jooservices\LaravelFlickr\Exceptions\RateLimitedException;
use Jooservices\LaravelFlickr\RateLimit\RequestLimiterInterface;

final class LimitingFlickrTransport implements FlickrTransportContract
{
    public function __construct(
        private readonly FlickrTransportContract $inner,
        private readonly RequestLimiterInterface $limiter,
        private readonly string $connectionKey,
    ) {}

    public function request(string $method, string $url, array $options = []): RawResponseData
    {
        $permit = $this->limiter->acquire($this->connectionKey);
        if (! $permit->acquired) {
            throw new RateLimitedException($permit->retryAfterSeconds);
        }
        $response = $this->inner->request($method, $url, $options);
        if ($response->statusCode === 429) {
            $seconds = $this->retryAfter($response);
            $this->limiter->triggerCooldown($this->connectionKey, $seconds);
            throw new RateLimitedException($seconds);
        }

        return $response;
    }

    private function retryAfter(RawResponseData $response): int
    {
        foreach ($response->headers as $name => $values) {
            if (strcasecmp($name, 'Retry-After') === 0 && isset($values[0]) && is_numeric($values[0])) {
                return max(1, (int) $values[0]);
            }
        }

        $seconds = config('laravel-flickr.rate_limit.cooldown_seconds', 3600);

        return is_int($seconds) ? $seconds : 3600;
    }
}
