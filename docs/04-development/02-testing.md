# Testing

## Real infrastructure (shared Docker images)

Tests that touch apps, tokens, persistence, OAuth pending, or Redis limiting require **MongoDB** and/or **Redis**. Prefer real services over fakes.

### Preferred: reuse host services (zero extra images)

This machine / org already runs the ecosystem tags:

| Service | Image tag (shared) | Typical port |
|---|---|---|
| MongoDB | `mongo:8.3.4` | `27017` |
| Redis | `redis:8.8.0-alpine` | `6379` |

If XFlickr, barista, or another stack already exposes those ports, **do not start another compose stack**. Point tests at them:

```bash
export MONGODB_URI=mongodb://127.0.0.1:27017
export MONGODB_DATABASE=jooservices_laravel_flickr_test
export REDIS_HOST=127.0.0.1
export REDIS_PORT=6379
export REDIS_DB=15
composer test
```

Use a **dedicated database name** and **Redis DB index** so package tests never wipe other projects’ data.

### Optional: package compose (same image tags only)

When nothing is listening on 27017/6379:

```bash
docker compose up -d   # pulls nothing extra if mongo:8.3.4 + redis:8.8.0-alpine already cached
composer test
```

- Images: **only** `mongo:8.3.4` and `redis:8.8.0-alpine` (same as XFlickr/barista).
- **No named volumes** — `tmpfs` for ephemeral test data (saves disk).
- Do **not** introduce `mongo:7` / `redis:7` in this package.

### CI

GitHub Actions uses the same image tags so layer cache stays aligned with the rest of the org.

## HTTP faking

HTTP to Flickr is faked through `jooservices/client` `ClientBuilder::fake` so the real transport + factory + job path still runs.

## Environment

| Variable | Purpose |
|---|---|
| `MONGODB_URI` | Default `mongodb://127.0.0.1:27017` |
| `MONGODB_DATABASE` | Default `jooservices_laravel_flickr_test` |
| `REDIS_HOST` / `REDIS_PORT` / `REDIS_DB` | Redis for limiter + pending OAuth |
| `REQUIRE_TEST_INFRA=1` | Fail instead of skip when services missing (CI sets this) |

## What we cover

- Happy paths for adapters, multi-connection scope, OAuth, jobs
- Unhappy paths: missing app/token, unknown adapters, rate denials, validation errors
- Security-relevant: OAuth callback validation, encrypted pending round-trip, rate-limit key hashing
- Edge: default `per_page` only on list methods, approaching event transition-once

## Quality

```bash
composer lint:all
composer test
composer ci   # coverage clover under build/coverage/
```

Do not add tests that only assert mocks without exercising package behaviour.
