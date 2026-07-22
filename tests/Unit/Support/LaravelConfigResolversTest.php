<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Support;

use JOOservices\LaravelConfig\Facades\Config;
use JOOservices\LaravelFlickr\Support\LaravelConfigRateLimitResolver;
use JOOservices\LaravelFlickr\Support\LaravelConfigRuntimeSettingsResolver;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class LaravelConfigResolversTest extends TestCase
{
    #[Test]
    public function rate_limit_resolver_reads_configured_values(): void
    {
        Config::fake([
            'flickr' => [
                'rate_limit_enabled' => 'false',
                'rate_limit_max_requests_per_hour' => '100',
                'rate_limit_min_gap_ms' => '250',
                'rate_limit_cooldown_seconds' => '30',
                'rate_limit_key_prefix' => 'custom-prefix',
                'rate_limit_warning_threshold_percent' => '90',
            ],
        ]);

        $resolver = new LaravelConfigRateLimitResolver();

        $this->assertFalse($resolver->enabled());
        $this->assertSame(100, $resolver->maxRequestsPerHour());
        $this->assertSame(250, $resolver->minGapMilliseconds());
        $this->assertSame(30, $resolver->cooldownSeconds());
        $this->assertSame('custom-prefix', $resolver->keyPrefix());
        $this->assertSame(90, $resolver->warningThresholdPercent());
    }

    #[Test]
    public function rate_limit_resolver_falls_back_for_invalid_or_empty_values(): void
    {
        Config::fake([
            'flickr' => [
                'rate_limit_enabled' => ['nope'],
                'rate_limit_max_requests_per_hour' => 'not-a-number',
                'rate_limit_min_gap_ms' => null,
                'rate_limit_cooldown_seconds' => new \stdClass(),
                'rate_limit_key_prefix' => '',
                'rate_limit_warning_threshold_percent' => false,
            ],
        ]);

        $resolver = new LaravelConfigRateLimitResolver();

        $this->assertTrue($resolver->enabled());
        $this->assertSame(3300, $resolver->maxRequestsPerHour());
        $this->assertSame(333, $resolver->minGapMilliseconds());
        $this->assertSame(3600, $resolver->cooldownSeconds());
        $this->assertSame('laravel-flickr:req', $resolver->keyPrefix());
        $this->assertSame(80, $resolver->warningThresholdPercent());
    }

    #[Test]
    public function runtime_settings_resolver_reads_configured_values(): void
    {
        Config::fake([
            'flickr' => [
                'default_connection' => 'primary',
                'cache_store' => 'redis',
                'cache_ttl_seconds' => '120',
                'oauth_pending_ttl_seconds' => '600',
                'oauth_pending_key_prefix' => 'oauth-custom',
                'queue_connection' => 'redis',
                'queue_name' => 'flickr-jobs',
                'logging_enabled' => 0,
                'events_enabled' => 'yes',
                'default_per_page' => '50',
            ],
        ]);

        $resolver = new LaravelConfigRuntimeSettingsResolver();

        $this->assertSame('primary', $resolver->defaultConnection());
        $this->assertSame('redis', $resolver->cacheStore());
        $this->assertSame(120, $resolver->cacheTtlSeconds());
        $this->assertSame(600, $resolver->oauthPendingTtlSeconds());
        $this->assertSame('oauth-custom', $resolver->oauthPendingKeyPrefix());
        $this->assertSame('redis', $resolver->queueConnection());
        $this->assertSame('flickr-jobs', $resolver->queueName());
        $this->assertFalse($resolver->loggingEnabled());
        $this->assertTrue($resolver->eventsEnabled());
        $this->assertSame(50, $resolver->defaultPerPage());
    }

    #[Test]
    public function runtime_settings_resolver_falls_back_for_empty_and_invalid_values(): void
    {
        Config::fake([
            'flickr' => [
                'default_connection' => '',
                'cache_store' => '',
                'cache_ttl_seconds' => 'bad',
                'oauth_pending_ttl_seconds' => [],
                'oauth_pending_key_prefix' => '',
                'queue_connection' => '',
                'queue_name' => '',
                'logging_enabled' => ['x'],
                'events_enabled' => new \stdClass(),
                'default_per_page' => null,
            ],
        ]);

        $resolver = new LaravelConfigRuntimeSettingsResolver();

        $this->assertSame('default', $resolver->defaultConnection());
        $this->assertNull($resolver->cacheStore());
        $this->assertSame(900, $resolver->cacheTtlSeconds());
        $this->assertSame(900, $resolver->oauthPendingTtlSeconds());
        $this->assertSame('laravel-flickr:oauth', $resolver->oauthPendingKeyPrefix());
        $this->assertNull($resolver->queueConnection());
        $this->assertSame('flickr', $resolver->queueName());
        $this->assertTrue($resolver->loggingEnabled());
        $this->assertTrue($resolver->eventsEnabled());
        $this->assertSame(100, $resolver->defaultPerPage());
    }
}
