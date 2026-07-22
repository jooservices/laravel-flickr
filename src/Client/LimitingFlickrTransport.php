<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Client;

use JOOservices\Flickr\Contracts\Client\FlickrTransportContract;
use JOOservices\Flickr\DTO\Common\RawResponseData;
use JOOservices\LaravelFlickr\Contracts\RateLimitConfigResolverInterface;
use JOOservices\LaravelFlickr\Events\FlickrRateLimitApproaching;
use JOOservices\LaravelFlickr\Exceptions\RateLimitedException;
use JOOservices\LaravelFlickr\RateLimit\DenyReason;
use JOOservices\LaravelFlickr\RateLimit\Permit;
use JOOservices\LaravelFlickr\RateLimit\RequestLimiterInterface;

final class LimitingFlickrTransport implements FlickrTransportContract
{
    /** @var array<string, float> last percent-used seen per connection key (for transition detection) */
    private array $lastPercentUsed = [];

    public function __construct(
        private readonly FlickrTransportContract $inner,
        private readonly RequestLimiterInterface $limiter,
        private readonly string $connectionKey,
        private readonly RateLimitConfigResolverInterface $rateLimitConfig,
    ) {}

    public function request(string $method, string $url, array $options = []): RawResponseData
    {
        $permit = $this->limiter->acquire($this->connectionKey);
        if (! $permit->acquired) {
            throw new RateLimitedException(
                $permit->retryAfterSeconds,
                $permit->reason ?? DenyReason::HourlyQuota,
                $this->connectionKey,
            );
        }

        $this->maybeWarnApproaching($permit);

        $response = $this->inner->request($method, $url, $options);
        if ($response->statusCode === 429) {
            $seconds = $this->retryAfter($response);
            $this->limiter->triggerCooldown($this->connectionKey, $seconds);
            throw new RateLimitedException($seconds, DenyReason::Cooldown, $this->connectionKey);
        }

        return $response;
    }

    private function maybeWarnApproaching(Permit $permit): void
    {
        $limit = $permit->limit;
        $remaining = $permit->remaining;
        if ($limit === null || $remaining === null || $limit < 1) {
            return;
        }

        $percentUsed = (($limit - $remaining) / $limit) * 100;
        $threshold = $this->rateLimitConfig->warningThresholdPercent();
        $previous = $this->lastPercentUsed[$this->connectionKey] ?? 0.0;
        $this->lastPercentUsed[$this->connectionKey] = $percentUsed;

        // Fire once on the transition across the threshold, not on every later request.
        if ($previous >= $threshold || $percentUsed < $threshold) {
            return;
        }

        event(new FlickrRateLimitApproaching(
            $this->connectionKey,
            $remaining,
            $limit,
            $percentUsed,
        ));
    }

    private function retryAfter(RawResponseData $response): int
    {
        foreach ($response->headers as $name => $values) {
            if (strcasecmp($name, 'Retry-After') === 0 && isset($values[0]) && is_numeric($values[0])) {
                return max(1, (int) $values[0]);
            }
        }

        return max(1, $this->rateLimitConfig->cooldownSeconds());
    }
}
