# Rate limiting

## Behaviour

- **Never sleeps.** Denied calls throw `RateLimitedException`.
- Enforced on every live HTTP call through `LimitingFlickrTransport` (built by `FlickrClientFactory`).
- Job middleware releases queued work with `retryAfter` seconds on denial; sync rethrows.
- Bucket key = **SHA-256 of the Flickr API key** (per app), not the raw key and not the NSID.
  Redis keyspace and events never embed the plaintext API key.

## Controls (`laravel-config`)

| Key | Default | Meaning |
|---|---|---|
| `flickr.rate_limit_enabled` | `true` | Live toggle (checked each acquire) |
| `flickr.rate_limit_max_requests_per_hour` | `3300` | Rolling hourly window |
| `flickr.rate_limit_min_gap_ms` | `333` | Min spacing between accepts |
| `flickr.rate_limit_cooldown_seconds` | `3600` | Default cooldown after 429 |
| `flickr.rate_limit_warning_threshold_percent` | `80` | Approaching event threshold |
| `flickr.rate_limit_key_prefix` | `laravel-flickr:req` | Redis key prefix |

## Status probe

```php
$status = app(FlickrService::class)
    ->connection('default')
    ->as($nsid) // or anonymous after app exists
    ->rateLimitStatus();

// remaining, limit, windowResetsAt, inCooldown, nextAllowedAt
```

CLI (operator visibility; defaults to `flickr.default_connection`):

```bash
php artisan flickr:rate-limit:status
php artisan flickr:rate-limit:status backup
```

## Events

| Event | When |
|---|---|
| `FlickrRateLimited` | Denial (middleware path also rethrows / releases) |
| `FlickrRateLimitApproaching` | **Once** when usage crosses the warning threshold for a connection key |

## Host override

Bind your own `RequestLimiterInterface` (or use `NullRequestLimiter` in special environments). Default binding is `RedisRequestLimiter` so `rate_limit_enabled` can flip without recycling workers.

### Queue uniqueness (not rate limiting)

By default queued Flickr jobs are **not** unique. Identical method/params can run concurrently or retry freely. Pass `unique: true` to `FlickrService::call()` / `FlickrJobDispatcher` to use `UniqueFlickrRequestJob` (`ShouldBeUnique`, 60 seconds).
