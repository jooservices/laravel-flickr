# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-07-21

### Added

- OAuth 1.0a `OAuthService` with explicit host-owned request-token storage and required NSID.
- OAuth begin result DTO, rate-limit contracts/DTOs, null limiter, and optional limiting transport.

## [1.0.0] - 2026-07-20

### Added

- Initial release: Laravel integration on top of `jooservices/flickr`.
- `FlickrClientFactory` for authenticated (force-auth) and anonymous SDK clients.
- Config credentials resolver (`laravel-flickr.php` + `FLICKR_API_*` env).
- Sync page fetch helpers: contacts, people photos, photosets, galleries, favorites.
- `TokenHealthProbe` for `flickr.test.login`.
- DTOs: `AppCredentials`, `OAuthToken`, `PageRequest`, `PagedResult`, `TokenHealthResult`.
- PHPUnit tests, Pint/PHPCS/PHPStan/PHPMD, GitHub CI + release workflows.

### Notes

- No queues, crawl engine, spider, catalog models, or UI (host responsibilities).

[1.0.0]: https://github.com/jooservices/laravel-flickr/releases/tag/v1.0.0
[1.1.0]: https://github.com/jooservices/laravel-flickr/releases/tag/v1.1.0
