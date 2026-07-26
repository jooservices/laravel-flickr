# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.0] - 2026-07-26

### Changed

- Require the published canonical dependency releases: `jooservices/laravel-repository` `^1.7`, `jooservices/laravel-events` `^1.5`, and `jooservices/laravel-logging` `^1.3`.
- Import repository APIs exclusively through `JOOservices\LaravelRepository\`.
- Align protected `master`/`develop` CI checks, release validation, Codecov, OpenSSF Scorecard, badges, and contributor guidance with the ecosystem release flow.

## [1.2.0] - 2026-07-26

### Added

- `Photos::getSizes($photoId)` adapter method for the single-request `flickr.photos.getSizes` API, available through `FlickrService` and the `Flickr` facade.

## [1.1.0] - 2026-07-25

### Added

- `Tags` adapter: `getListUser`, `getListUserPopular`, `getListUserRaw`, `getHotList`, `getListPhoto`, `getRelated`.
- `flickr:rate-limit:status` Artisan command for per-connection limiter status.
- `JOOservices\LaravelFlickr\Facades\Flickr` facade with package alias discovery (`Flickr`) and container alias `flickr`.
- `FlickrCallService` — single-call execution + lifecycle events (job is a thin shell).
- `OAuthCompletionService` — shared OAuth complete path for CLI and HTTP callback.
- `FlickrAdapterRegistry` — single namespace → adapter class map for service + persist listener.
- `UniqueFlickrRequestJob` / `call(..., unique: true)` — opt-in 60s queue uniqueness (default job is **not** unique).
- `MongoBulkUpsert` for page-level photo/contact/group/favorite persistence.
- `PersistenceReconcileService` — soft-remove stale rows and emit domain removal events.
- Optional call `correlationId` on jobs / lifecycle events; richer failure logs.
- Rate-limit approaching transition state is cache-backed (multi-worker safe).

### Changed

- Removed Guzzle/promises version aliases; require `jooservices/client` `^2.1` (Guzzle 7.10+ or 8).
- `FlickrService` constructor injects `RequestLimiterInterface` (no `app()` in `rateLimitStatus()`).
- `FlickrRequestJob` constructor is side-effect free; queue connection/name applied in `FlickrJobDispatcher`.
- `RedisRequestLimiter::status()` pipelines read commands after window prune (WITHSCORES normalized).
- OAuth callback returns structured 404 when the pending app is missing.
- Repositories no longer fire domain removal events (service owns that).

### Fixed

- `PersistFlickrData` no-ops unknown namespaces (escape-hatch `call()` no longer fails after a successful Flickr response).
- Default queued jobs are no longer silently dropped via `ShouldBeUnique`.

### Dependencies

- `jooservices/client` `^2.1` (direct floor; Guzzle resolved via Laravel 13 + client dual-range).

## [1.0.0] - 2026-07-22

### Added

- Initial public release of `jooservices/laravel-flickr`.
- `FlickrService` entry point with multi-app `connection($name)`, `as($nsid)`, and `anonymous()` scopes.
- Namespace adapters: `Photos`, `People`, `Contacts`, `Photosets`, `Galleries`, `Favorites`, `Test`.
- Shared `FlickrRequestJob` (sync by default, queue opt-in per call) with rate-limit middleware.
- Multi Flickr API app storage in MongoDB (`flickr_apps`) with encrypted credentials.
- OAuth 1.0a token storage per `(app_name, nsid)` with encrypted secrets (`flickr_tokens`).
- OAuth CLI (`flickr:app:add`, `flickr:oauth:*`), HTTP callback + FormRequest validation, encrypted Redis pending state.
- Redis rate limiter (hourly quota, min-gap, cooldown) via `LimitingFlickrTransport`; never sleeps.
- Runtime settings and rate-limit thresholds via `jooservices/laravel-config` (`flickr.*` flat keys).
- Package env config limited to OAuth callback path (`FLICKR_OAUTH_CALLBACK_PATH`).
- Lifecycle events + listeners for activity logging, event sourcing, and optional Mongo persistence.
- Standalone `ActivityLogService` / `StoredEventService` (not exposed on `FlickrService`).
- Doctor, index install, and quality tooling (Pint, PHPCS, PHPStan max, PHPMD, PHPUnit).
- Rate-limit connection identity hashed (SHA-256 of API key); OAuth callback throttled.
- Shared test infra images (`mongo:8.3.4`, `redis:8.8.0-alpine`) — no package-local mongo:7/redis:7 layers.

### Dependencies

- PHP 8.5+, Laravel illuminate `^13.0`
- `jooservices/flickr` ^2.0, ecosystem packages, `mongodb/laravel-mongodb`
- Guzzle 8 via Composer alias (removed in 1.1.0)

[1.3.0]: https://github.com/jooservices/laravel-flickr/releases/tag/v1.3.0
[1.2.0]: https://github.com/jooservices/laravel-flickr/releases/tag/v1.2.0
[1.1.0]: https://github.com/jooservices/laravel-flickr/releases/tag/v1.1.0
[1.0.0]: https://github.com/jooservices/laravel-flickr/releases/tag/v1.0.0
