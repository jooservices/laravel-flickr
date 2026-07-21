# Coding standards

Aligned with JOOservices `dto` / `laravel-identity` quality bars and common Laravel practice.

## Principles

- SOLID, DRY, KISS, YAGNI
- Prefer small final classes with clear SRP
- Interfaces only at package seams hosts may replace
- No multi-page loops or queue side effects

## Quality gates

```bash
composer lint:all   # pint, phpcs, phpstan, phpmd
composer test
composer check      # lint:all + test
composer ci         # lint:all + coverage
```

## Naming

- Services/fetchers: `*Fetcher`, `*Factory`, `*Probe`
- DTOs under `Dto/`
- Contracts under `Contracts/`
- Exceptions under `Exceptions/`

## PHP / Laravel

- `declare(strict_types=1);`
- PHP 8.5+
- Constructor property promotion
- Readonly DTOs where immutable
