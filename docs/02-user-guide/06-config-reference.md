# Config reference

## Package env / file config

File: `config/laravel-flickr.php` (publish tag `laravel-flickr-config`).

| Key | Env | Default |
|---|---|---|
| `oauth.callback_path` | `FLICKR_OAUTH_CALLBACK_PATH` | `api/v1/oauth/flickr/callback` |

Boot-bound route only. Everything else is runtime via laravel-config.

## laravel-config flat keys (`flickr` group)

| Key | Default | Purpose |
|---|---|---|
| `flickr.default_connection` | `default` | Default app name |
| `flickr.rate_limit_enabled` | `true` | Enable Redis limiter |
| `flickr.rate_limit_max_requests_per_hour` | `3300` | Hourly quota |
| `flickr.rate_limit_min_gap_ms` | `333` | Min gap between accepts |
| `flickr.rate_limit_cooldown_seconds` | `3600` | Default 429 cooldown |
| `flickr.rate_limit_key_prefix` | `laravel-flickr:req` | Redis prefix for limiter |
| `flickr.rate_limit_warning_threshold_percent` | `80` | Approaching event threshold |
| `flickr.cache_store` | `null` | Laravel cache store name (null = default) |
| `flickr.cache_ttl_seconds` | `900` | SDK public cache TTL |
| `flickr.oauth_pending_ttl_seconds` | `900` | Pending OAuth Redis TTL |
| `flickr.oauth_pending_key_prefix` | `laravel-flickr:oauth` | Pending OAuth Redis prefix |
| `flickr.queue_connection` | `null` | Queue connection (null = default) |
| `flickr.queue_name` | `flickr` | Queue name for jobs |
| `flickr.logging_enabled` | `true` | Activity listener |
| `flickr.events_enabled` | `true` | Event-sourcing listener |
| `flickr.default_per_page` | `100` | Injected for list-style adapter methods only |

Hosts set these through `jooservices/laravel-config` APIs (memory-cached, live without restart).
