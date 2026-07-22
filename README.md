# JOOservices Laravel Flickr

[![PHP Version](https://img.shields.io/badge/PHP-8.5%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

Laravel integration **on top of** [`jooservices/flickr`](https://github.com/jooservices/flickr): multi-app credentials, `FlickrService` entry point, domain adapters, shared request job (sync default / queue opt-in), MongoDB tokens + optional persistence, OAuth CLI/callback, rate limits, and ecosystem logging/events.

**Package name:** `jooservices/laravel-flickr`  
**Namespace:** `JOOservices\LaravelFlickr`  
**Version:** 1.0.0

## Charter

| In scope | Out of scope |
|---|---|
| `FlickrService` + namespace adapters | Multi-page walks inside adapters |
| Shared `FlickrRequestJob` (sync or queued) | Crawl run / spider frontier |
| Multi Flickr API apps + tokens in MongoDB | Host UI / Inertia / Blade |
| OAuth 1.0a (CLI + HTTP callback) | Sleeping for rate limits |
| Rate-limit middleware + status probe | Exposing `activities` / `logs` / `events` on `FlickrService` |
| Listeners for logging, events, persistence | |

Hosts own multi-page orchestration and product workflows. Resolve `ActivityLogService` / `StoredEventService` from the container when needed.

## Principles

- SOLID, DRY, KISS, YAGNI
- Patterns only when justified (Factory, Decorator, thin adapters)
- Host layering: `Controller → FormRequest → Service → (this package) → Repository`

## Install

```bash
composer require jooservices/laravel-flickr:^1.0
```

Requires PHP 8.5+, Laravel illuminate `^13.0`, MongoDB, and Redis (when rate limiting / OAuth pending are used).

Publish config (optional — only OAuth callback path):

```bash
php artisan vendor:publish --tag=laravel-flickr-config
```

```env
FLICKR_OAUTH_CALLBACK_PATH=api/v1/oauth/flickr/callback
```

All other settings live in `jooservices/laravel-config` under the `flickr` group (flat `group.key` paths). See [docs/02-user-guide/06-config-reference.md](docs/02-user-guide/06-config-reference.md).

Register at least one Flickr API app, then authorize accounts:

```bash
php artisan flickr:app:add default --api-key=… --api-secret=…
php artisan flickr:oauth:authorize default
php artisan flickr:install-indexes
php artisan flickr:doctor
```

## Usage

```php
use JOOservices\LaravelFlickr\Service\FlickrService;

$response = app(FlickrService::class)
    ->as($nsid) // uses flickr.default_connection
    ->contacts
    ->getList(['per_page' => 100]);

// Explicit API app / connection:
app(FlickrService::class)
    ->connection('backup')
    ->as($nsid)
    ->photos
    ->getInfo($photoId);
```

Anonymous probes (still need a registered app for the API key):

```php
app(FlickrService::class)
    ->anonymous()
    ->people
    ->getPublicPhotos($nsid, ['per_page' => 5]);
```

### Adapters

| Adapter | Typical methods |
|---|---|
| `Photos` | photo queries / info |
| `People` | `getPublicPhotos`, profile helpers |
| `Contacts` | `getList`, public list |
| `Photosets` | `getList`, photos page |
| `Galleries` | `getList`, photos page |
| `Favorites` | `getList` |
| `Test` | `login` |

Each adapter method performs **one** Flickr call (optionally `$queued = true`).

### Activity / events (not on FlickrService)

```php
use JOOservices\LaravelFlickr\Service\ActivityLogService;
use JOOservices\LaravelFlickr\Service\StoredEventService;

app(ActivityLogService::class); // laravel-logging backed
app(StoredEventService::class); // laravel-events backed
```

### OAuth

Package owns pending-authorization storage, CLI (`flickr:app:add`, `flickr:oauth:*`), and the HTTP callback. Apps (`api_key` / `api_secret`) and tokens land in MongoDB (encrypted at rest).

### Rate limiting

Default Redis limiter when enabled via laravel-config. Denials throw `RateLimitedException` (no sleep). Inspect quota with `FlickrService::rateLimitStatus()` (scoped to the active connection’s API key).

## Documentation

Full guides: [docs/README.md](docs/README.md) — architecture diagrams, models, flows, config, testing.

## Stack

```text
Host app (orchestration, UI)
        ↓
jooservices/laravel-flickr   ← FlickrService, adapters, OAuth, limits, listeners
        ↓
jooservices/flickr           ← SDK
```

## Quality

```bash
# Prefer an already-running shared Mongo/Redis (mongo:8.3.4 + redis:8.8.0-alpine).
# Optional: docker compose up -d  (same image tags only; tmpfs, no extra volumes)
composer test
composer lint
composer check
```

See [docs/04-development/02-testing.md](docs/04-development/02-testing.md) and the [quality deep dive](docs/04-development/03-quality-deep-dive.md).

## License

MIT — see [LICENSE](LICENSE).
