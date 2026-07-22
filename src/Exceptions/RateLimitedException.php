<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Exceptions;

use JOOservices\Exceptions\Concerns\HasExceptionContext;
use JOOservices\Exceptions\Contracts\ContextAwareExceptionInterface;
use JOOservices\LaravelFlickr\RateLimit\DenyReason;

final class RateLimitedException extends LaravelFlickrRuntimeException implements ContextAwareExceptionInterface
{
    use HasExceptionContext;

    public function __construct(
        public readonly int $retryAfterSeconds,
        public readonly DenyReason $denyReason,
        public readonly string $connectionKey,
        string $message = '',
    ) {
        parent::__construct(
            $message !== ''
                ? $message
                : "Flickr request denied ({$denyReason->value}); retry after {$retryAfterSeconds}s.",
        );
        $this->initContext([
            'retryAfterSeconds' => $this->retryAfterSeconds,
            'denyReason' => $this->denyReason->value,
            'connectionKey' => $this->connectionKey,
        ]);
    }
}
