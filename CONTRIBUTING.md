# Contributing

## Setup

```bash
composer install
composer check
```

## Standards

- SOLID, DRY, KISS, YAGNI
- Sync I/O only — no queues in this package
- Page-level APIs only — no “sync all pages” helpers
- Pint, PHPCS, PHPStan, PHPMD, PHPUnit must pass

## Pull requests

1. Branch from `main`
2. Keep scope focused
3. Add/adjust unit tests
4. Update `CHANGELOG.md` under `[Unreleased]` when applicable
