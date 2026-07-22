# Coding standards

Aligned with JOOservices quality bars and common Laravel practice.

## Principles

- SOLID, DRY, KISS, YAGNI
- Prefer small final classes with clear SRP
- Interfaces only at package seams hosts may replace
- No multi-page loops; never sleep for rate limits
- Sync by default; queue only via explicit `$queued = true` on adapter calls
- Multi-app aware: never assume a single global API key from env

## Quality gates

```bash
composer lint:all   # pint, phpcs, phpstan, phpmd
composer test
composer check      # lint:all + test
composer ci         # lint:all + coverage
```

## Naming

- Entry: `FlickrService`; domain adapters under `Adapters/`
- Jobs / middleware under `Jobs/`
- Repositories under `Repositories/`; models under `Models/`
- DTOs under `Dto/`
- Contracts under `Contracts/`
- Exceptions under `Exceptions/`
- HTTP FormRequests under `Http/Requests/`
- Standalone `ActivityLogService` / `StoredEventService` (not on `FlickrService`)

## PHP / Laravel

- `declare(strict_types=1);`
- PHP 8.5+ / Laravel illuminate `^13.0`
- Namespace `JOOservices\LaravelFlickr`
- Constructor property promotion
- Readonly DTOs where immutable
