<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Support;

use JOOservices\LaravelFlickr\Contracts\RuntimeSettingsResolverInterface;

/**
 * Reads flat `flickr.*` keys from laravel-config (group.key paths only).
 */
final class LaravelConfigRuntimeSettingsResolver implements RuntimeSettingsResolverInterface
{
    public function defaultConnection(): string
    {
        return LaravelConfigValue::string('flickr.default_connection', 'default');
    }

    public function cacheStore(): ?string
    {
        return LaravelConfigValue::nullableString('flickr.cache_store');
    }

    public function cacheTtlSeconds(): int
    {
        return LaravelConfigValue::int('flickr.cache_ttl_seconds', 900);
    }

    public function oauthPendingTtlSeconds(): int
    {
        return LaravelConfigValue::int('flickr.oauth_pending_ttl_seconds', 900);
    }

    public function queueConnection(): ?string
    {
        return LaravelConfigValue::nullableString('flickr.queue_connection');
    }

    public function queueName(): string
    {
        return LaravelConfigValue::string('flickr.queue_name', 'flickr');
    }

    public function loggingEnabled(): bool
    {
        return LaravelConfigValue::bool('flickr.logging_enabled', true);
    }

    public function eventsEnabled(): bool
    {
        return LaravelConfigValue::bool('flickr.events_enabled', true);
    }

    public function defaultPerPage(): int
    {
        return LaravelConfigValue::int('flickr.default_per_page', 100);
    }

    public function oauthPendingKeyPrefix(): string
    {
        return LaravelConfigValue::string('flickr.oauth_pending_key_prefix', 'laravel-flickr:oauth');
    }
}
