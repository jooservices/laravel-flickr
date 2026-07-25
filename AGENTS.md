# AGENTS.md — jooservices/laravel-flickr

## Core intent

- Laravel integration on top of `jooservices/flickr` ^2 + `jooservices/client` ^2.1.
- Entry point: `FlickrService` (`connection($name)?` → `as($nsid)` / `anonymous()`, then adapters).
  Also: facade `JOOservices\LaravelFlickr\Facades\Flickr` and container alias `app('flickr')`.
- Sync by default; queue opt-in per call via the shared `FlickrRequestJob`.
- Owns OAuth1 (CLI + one HTTP callback), multi Flickr API apps + token storage (MongoDB),
  rate limits, events, optional persistence, and thin read services over
  `laravel-logging` / `laravel-events`.
- Principles: **SOLID, DRY, KISS, YAGNI**. Explicit over clever.
- Host layering: `Controller → FormRequest → Service → Repository`; hosts call this package from Services.
- Namespace: `JOOservices\LaravelFlickr` (capital OO).

## Public surface

- `FlickrService` — Flickr-only (`connection` / `as` / `anonymous` / adapters / `call` / `getClient` / `rateLimitStatus`)
- `FlickrCallService` — single-call execution + lifecycle events (used by `FlickrRequestJob`)
- `ActivityLogService` / `StoredEventService` — standalone container services (not on `FlickrService`)
- `PersistenceReconcileService` — soft-remove stale Mongo rows + domain events
- `OAuthCompletionService` — shared OAuth complete path (CLI + HTTP callback)
- `FlickrClientFactory` / `FlickrClientFactoryInterface`
- `AppRepository` / `TokenRepository` — MongoDB Flickr API apps + OAuth tokens
- Rate-limit / runtime settings via `laravel-config` (`flickr.*` flat keys)
- Adapters: `Photos`, `People`, `Contacts`, `Photosets`, `Galleries`, `Favorites`, `Test`, `Tags` (via `FlickrAdapterRegistry`)
- DTOs: `AppCredentials`, `FlickrApp`, `OAuthToken`, `OAuthBeginResult`, `OAuthCompleteResult`, `FlickrCallOutcome`
- Package env config: only `oauth.callback_path` (`FLICKR_OAUTH_CALLBACK_PATH`)
- Queue: default `FlickrRequestJob` is **not** unique; opt-in `UniqueFlickrRequestJob` / `call(..., unique: true)`
- CLI: `flickr:app:add`, `flickr:oauth:*`, `flickr:install-indexes`, `flickr:doctor`, `flickr:rate-limit:status`

## Hard rules

1. Never sleep for rate limits inside this package — deny via `RateLimitedException` / events.
2. Never loop all pages of a Flickr list API inside adapters; page-level only.
3. Account-bound clients go through the factory (force-auth); resolve tokens only in `FlickrService::getClient()` and `FlickrCallService`.
4. Anonymous probes use `anonymous()` explicitly (requires a registered app / connection).
5. Do not expose `->activities` / `->logs` / `->events` on `FlickrService` — hosts resolve `ActivityLogService` / `StoredEventService` (or upstream query APIs) directly.
6. Default `per_page` applies only to list-style adapter methods (`applyDefaultPerPage: true`), never auth probes like `test.login`.
7. `FlickrRateLimitApproaching` fires once on threshold transition, not on every later request.
8. `PersistFlickrData` must no-op unknown namespaces (never throw after a successful Flickr HTTP call).
9. Keep a single adapter registry (`FlickrAdapterRegistry`) for service + persist paths.

## Quality

```bash
# Shared infra: mongo:8.3.4 + redis:8.8.0-alpine (do not introduce mongo:7/redis:7)
composer test
composer lint
composer lint:all
composer check
composer ci
```

Prefer fixing smells over suppressions.

## Docs map

| Path | Use |
|---|---|
| `docs/` | Human product + architecture docs |
| `AGENTS.md` | This file — agent source of truth |
| `ai/skills/README.md` | Short checklist (points here) |
| `CHANGELOG.md` | Release notes |
| `CONTRIBUTING.md` | PR / branch expectations |
