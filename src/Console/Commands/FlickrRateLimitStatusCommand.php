<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Console\Commands;

use Illuminate\Console\Command;
use JOOservices\LaravelFlickr\Contracts\RuntimeSettingsResolverInterface;
use JOOservices\LaravelFlickr\RateLimit\RequestLimiterInterface;
use JOOservices\LaravelFlickr\Repositories\AppRepository;
use JOOservices\LaravelFlickr\Support\RateLimitConnectionKey;

final class FlickrRateLimitStatusCommand extends Command
{
    protected $signature = 'flickr:rate-limit:status
                            {connection? : App connection name (defaults to configured default)}';

    protected $description = 'Show Redis rate-limit bucket status for a Flickr API app connection';

    public function handle(
        AppRepository $apps,
        RuntimeSettingsResolverInterface $runtime,
        RequestLimiterInterface $limiter,
    ): int {
        $raw = $this->argument('connection');
        $appName = is_string($raw) && $raw !== ''
            ? $raw
            : $runtime->defaultConnection();

        $app = $apps->find($appName);
        if ($app === null) {
            $this->error("Flickr app [{$appName}] is not registered. Run flickr:app:add {$appName}");

            return self::FAILURE;
        }

        $status = $limiter->status(
            RateLimitConnectionKey::fromApiKey($app->apiKey),
            fresh: true,
        );

        $this->table(
            ['Field', 'Value'],
            [
                ['connection', $appName],
                ['remaining', (string) $status->remaining],
                ['limit', (string) $status->limit],
                ['window_resets_at', $status->windowResetsAt->toIso8601String()],
                ['in_cooldown', $status->inCooldown ? 'yes' : 'no'],
                ['cooldown_expires_at', $status->cooldownExpiresAt?->toIso8601String() ?? '—'],
                ['next_allowed_at', $status->nextAllowedAt?->toIso8601String() ?? '—'],
            ],
        );

        return self::SUCCESS;
    }
}
