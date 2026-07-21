# JOOservices Laravel Flickr

[![PHP Version](https://img.shields.io/badge/PHP-8.5%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

Laravel integration **on top of** [`jooservices/flickr`](https://github.com/jooservices/flickr): account-bound SDK clients and **sync, page-level** fetch helpers (contacts, people photos, photosets, galleries, favorites, token health).

**Package name:** `jooservices/laravel-flickr`

## Charter

| In scope | Out of scope |
|---|---|
| Client factory (authenticated / anonymous) | Queues / Horizon / jobs |
| Page-level fetch helpers | Crawl run / target state machine |
| Force-auth for connection clients | Spider / frontier |
| Config credentials resolver | Catalog DB models / migrations |
| Typed DTOs (`PagedResult`, tokens) | UI / Inertia / Blade |
| OAuth 1.0a helper and optional rate-limit transport | Token persistence |

Host apps own multi-page walks, persistence, retries, and product workflows.

## Principles

- SOLID, DRY, KISS, YAGNI
- Patterns only when justified (Factory, Decorator, Strategy via fetchers)
- Layering for hosts: `Controller → FormRequest → Service → (this package) → Repository`
- No god managers, no queue side effects

## Install

```bash
composer require jooservices/laravel-flickr
```

Until Packagist is live, use VCS:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/jooservices/laravel-flickr"
    }
  ],
  "require": {
    "jooservices/laravel-flickr": "^1.0"
  }
}
```

Publish config (optional):

```bash
php artisan vendor:publish --tag=laravel-flickr-config
```

```env
FLICKR_API_KEY=
FLICKR_API_SECRET=
FLICKR_DEFAULT_PER_PAGE=100
FLICKR_RATE_LIMIT_ENABLED=true
```

## Usage

```php
use Jooservices\LaravelFlickr\Client\FlickrClientFactory;
use Jooservices\LaravelFlickr\Dto\AppCredentials;
use Jooservices\LaravelFlickr\Dto\OAuthToken;
use Jooservices\LaravelFlickr\Fetch\ContactsFetcher;

$credentials = new AppCredentials($apiKey, $apiSecret);
$token = OAuthToken::fromArray([
    'oauth_token' => $oauthToken,
    'oauth_token_secret' => $oauthSecret,
    'user_nsid' => $nsid,
]);

$client = app(FlickrClientFactory::class)->authenticated($credentials, $token);
// or: ->authenticatedFromConfig($token);

$page = app(ContactsFetcher::class)->listPage($client, page: 1, perPage: 100);

if ($page->ok) {
    foreach ($page->items as $contact) {
        // host persists / enqueues next work
    }
    if ($page->hasMorePages()) {
        // host schedules page + 1 — never inside this package
    }
}
```

### Fetch helpers

| Class | Methods |
|---|---|
| `ContactsFetcher` | `listPage`, `publicListPage` |
| `PeoplePhotosFetcher` | `listPage`, `publicListPage` |
| `PhotosetsFetcher` | `listPage`, `photosPage` |
| `GalleriesFetcher` | `listPage`, `photosPage` |
| `FavoritesFetcher` | `listPage` |
| `TokenHealthProbe` | `probe` |

All helpers return `PagedResult` (or `TokenHealthResult`) and perform **one** HTTP request.

### Anonymous probes

```php
$anon = app(FlickrClientFactory::class)->anonymousFromConfig();
app(PeoplePhotosFetcher::class)->publicListPage($anon, $nsid, 1, 5);
```

### OAuth connect

`OAuthService` is synchronous and does not use a session or persist tokens. The host must store the request-token secret between requests and validate the callback token before calling `complete()`.

```php
use Jooservices\LaravelFlickr\OAuth\OAuthService;

$begin = app(OAuthService::class)->begin($credentials);
// Persist $begin->requestTokenSecret in the host session; redirect to $begin->authorizationUrl.
$token = app(OAuthService::class)->complete($credentials, $callbackToken, $verifier, $requestTokenSecret);
```

`complete()` rejects an access-token response without a Flickr NSID.

### Optional rate limiting

The package binds `RedisRequestLimiter` by default when `FLICKR_RATE_LIMIT_ENABLED=true`; bind `RequestLimiterInterface` to a host implementation when policy differs. Wrap an SDK transport with `LimitingFlickrTransport`. The decorator never sleeps, queues work, logs tokens, or persists telemetry; it throws `RateLimitedException` for a local denial or HTTP 429.

## Stack

```text
Host app (queues, spider, catalog)
        ↓
jooservices/laravel-flickr   ← this package (sync I/O helpers)
        ↓
jooservices/flickr           ← SDK
```

## Quality

```bash
composer test
composer lint
composer lint:all
composer check
composer ci
```

## Docs

- [Architecture overview](docs/00-architecture/01-overview.md)
- [Installation](docs/01-getting-started/01-installation.md)
- [Quick start](docs/01-getting-started/02-quick-start.md)
- [Coding standards](docs/04-development/02-coding-standards.md)

## License

MIT — see [LICENSE](LICENSE).
