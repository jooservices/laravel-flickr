# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
- Guzzle 8 via Composer alias (Laravel still declares Guzzle 7)

[1.0.0]: https://github.com/jooservices/laravel-flickr/releases/tag/v1.0.0
