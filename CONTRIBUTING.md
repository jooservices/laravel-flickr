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
- Default `FlickrRequestJob` is **not** unique; opt-in via `unique: true`
- Page-level APIs only — no “sync all pages” helpers
- Never sleep for rate limits
- Multi-app: tokens and clients always resolve through a named connection
- Call orchestration lives in `FlickrCallService`, not the job body
- Pint, PHPCS, PHPStan, PHPMD, PHPUnit must pass

## Git workflow

This repository uses protected **`develop`** for integration and protected **`master`** for production releases.

| Work | Branch | PR into |
|---|---|---|
| Feature / fix | `feature/*` or `fix/*` from `develop` | `develop` |
| Release prep | `release/X.Y.Z` from `develop` | `master` |
| Hotfix | `hotfix/*` from `master` | `master`, then back-merge to `develop` |

Release steps:

1. Land all feature work on `develop` through a green PR.
2. On `release/X.Y.Z`: finalize `CHANGELOG.md`, README version/badges, docs.
3. Open the release PR into `master` and merge only when every protected check is green.
4. Wait for post-merge `master` CI, then create and push annotated tag `vX.Y.Z`.
5. Wait for `release.yml` to validate the tag and create the GitHub Release.
6. Back-merge the released `master` state into `develop` through a green PR.

Use Conventional Commits where practical (`feat:`, `fix:`, `docs:`, `chore(release):`).

## Pull requests

1. Branch from `develop`
2. Keep scope focused
3. Add/adjust tests (happy, unhappy, edge, security-relevant)
4. Update docs when behaviour or public surface changes
5. Update `CHANGELOG.md` under `[Unreleased]` when applicable (move to version section on release)

## Docs

| Path | Role |
|---|---|
| `docs/` | Canonical product docs |
| `AGENTS.md` | Agent rules |
| `ai/skills/README.md` | Short agent checklist |
| `README.md` | Install + quick usage + badges |
| `CHANGELOG.md` | Release notes |
