# Contributing

## Setup

```bash
composer install
# Prefer shared host Mongo/Redis (same tags as XFlickr/barista: mongo:8.3.4, redis:8.8.0-alpine).
# Only if ports are free: docker compose up -d
composer check
```

Set `REQUIRE_TEST_INFRA=1` to fail (instead of skip) when Mongo/Redis are unavailable.

## Standards

- SOLID, DRY, KISS, YAGNI
- Sync I/O is the default; queue only via explicit `$queued = true` on adapter / `call` paths
- Page-level APIs only — no “sync all pages” helpers
- Never sleep for rate limits
- Multi-app: tokens and clients always resolve through a named connection
- Pint, PHPCS, PHPStan, PHPMD, PHPUnit must pass

## Pull requests

1. Branch from `main`
2. Keep scope focused
3. Add/adjust tests (happy, unhappy, edge, security-relevant)
4. Update docs when behaviour or public surface changes
5. Update `CHANGELOG.md` under `[Unreleased]` when applicable

## Docs

Canonical product docs live under `docs/`. Agent rules live in `AGENTS.md`.
