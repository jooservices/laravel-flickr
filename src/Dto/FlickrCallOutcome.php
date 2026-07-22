<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Dto;

use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\LaravelFlickr\Events\FlickrCallCompleted;

/**
 * Outcome payload for a completed Flickr API call (keeps {@see FlickrCallCompleted} constructor lean).
 */
final readonly class FlickrCallOutcome
{
    public function __construct(
        public bool $ok,
        public int $itemCount,
        public float $durationMs,
        public int $quotaRemaining,
        public int $quotaLimit,
        public ApiResponseData $response,
    ) {}
}
