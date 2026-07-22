<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Contracts;

interface RuntimeSettingsResolverInterface
{
    public function defaultConnection(): string;

    public function cacheStore(): ?string;

    public function cacheTtlSeconds(): int;

    public function oauthPendingTtlSeconds(): int;

    public function oauthPendingKeyPrefix(): string;

    public function queueConnection(): ?string;

    public function queueName(): string;

    public function loggingEnabled(): bool;

    public function eventsEnabled(): bool;

    public function defaultPerPage(): int;
}
