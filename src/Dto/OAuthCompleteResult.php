<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Dto;

/**
 * Outcome of completing a pending OAuth authorization (CLI or HTTP callback).
 */
final readonly class OAuthCompleteResult
{
    public function __construct(
        public bool $ok,
        public ?OAuthToken $token = null,
        public ?string $appName = null,
        public ?string $correlationId = null,
        public ?string $error = null,
        public int $status = 200,
    ) {}

    public static function success(OAuthToken $token, string $appName, ?string $correlationId): self
    {
        return new self(
            ok: true,
            token: $token,
            appName: $appName,
            correlationId: $correlationId,
        );
    }

    public static function failure(string $error, int $status): self
    {
        return new self(ok: false, error: $error, status: $status);
    }
}
