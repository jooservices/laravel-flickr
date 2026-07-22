# Quality deep dive (1.0.0)

Living checklist for code quality, performance, security, and documentation readiness.

## 1. Architecture quality

| Area | Assessment | Notes |
|---|---|---|
| SOLID | Strong | Factory, decorator transport, repository seams, resolver interfaces |
| DRY | Strong | Shared job dispatcher, config value helpers, response item normalizer |
| KISS | Strong | Single job for sync/queue; no crawl engine |
| YAGNI | Strong | Page-level only; no multi-page adapters |
| Patterns | Appropriate | Factory, Decorator, Adapter, Repository, DTO — no pattern theater |

### Hard rules (enforced)

1. Never sleep for rate limits  
2. Never multi-page loop in adapters  
3. Tokens resolved only in `getClient()` / job `handle()`  
4. Anonymous requires registered app  
5. No `activities`/`logs`/`events` on `FlickrService`  
6. Default `per_page` only on list methods  
7. Approaching event fires on **transition** only  

## 2. Security model

### At rest

| Secret | Store | Protection |
|---|---|---|
| App API key/secret | Mongo `flickr_apps` | Laravel `encrypted` cast (`APP_KEY`) |
| OAuth access token/secret | Mongo `flickr_tokens` | Laravel `encrypted` cast |
| OAuth request token secret | Redis pending | `Crypt::encryptString` + TTL + atomic consume |

### In transit / process

| Surface | Protection |
|---|---|
| Queue job payload | Carries `appName` + `nsid` only — **never** OAuth secrets |
| Rate-limit Redis keys | SHA-256 of API key via `RateLimitConnectionKey` (key material not in Redis keyspace) |
| Events (`FlickrOAuthCompleted`, etc.) | No bearer credentials |
| OAuth HTTP callback | FormRequest validation + `throttle:30,1` |
| Force-auth client | Authenticated REST forced for account-scoped clients |

### Host obligations

- Protect `APP_KEY`, MongoDB, and Redis  
- Use HTTPS for public OAuth callback  
- Isolate test Redis DB / Mongo database names  

### Residual risks (accepted / host-owned)

- Reflection into SDK private client field (`ForceAuthenticatedFlickr`) — fragile across SDK majors; covered by factory tests  
- Activity log may store Flickr exception **messages** (ops value; avoid logging full params with PII if hosts extend listeners)  
- API key still lives in process memory when building clients (unavoidable for signing)  

## 3. Performance

| Path | Design | Status |
|---|---|---|
| Rate-limit claim | Atomic Redis Lua (min-gap + hourly) | Good |
| Completed-call quota | In-memory acquire snapshot (`fresh: false`) | Good |
| Snapshot map | Capped at 64 keys per worker | Good |
| Photo membership | Pivot collections first, then `whereIn` photos | Good |
| Upsert page | N `updateOrCreate` per page | Acceptable for page-level; hosts must not dump full library through one call |
| Config | laravel-config memory cache (upstream) | Live without restart |
| Limiter disabled | Checked per acquire — no worker recycle | Good |

### Non-goals (deliberate)

- Multi-page crawls  
- Sleep/backoff inside package  
- Bulk Mongo write API for hosts  

## 4. Test posture

| Metric | Target | Approach |
|---|---|---|
| Real Mongo/Redis | Required for infra tests | Shared ecosystem images; dedicated DB/DB index |
| HTTP | Fake via client kit | Full factory → transport → job path |
| CI | Fail if infra missing | `REQUIRE_TEST_INFRA=1` |
| Coverage | High line coverage on `src/` | PHPUnit clover |

Suites include happy / unhappy / validation / multi-app / rate-limit transition / OAuth security paths.

## 5. Documentation map

| Doc | Purpose |
|---|---|
| `00-architecture/*` | Stack, class map, flows, models |
| `01-getting-started/*` | Install + quick start |
| `02-user-guide/*` | Adapters, multi-app, OAuth, limits, events, config |
| `04-development/*` | Standards, testing (shared Docker), this deep dive |

## 6. Ops checklist before tagging 1.0.0

- [ ] Host Mongo + Redis using shared tags (`mongo:8.3.4`, `redis:8.8.0-alpine`)  
- [ ] `composer lint:all && composer test` green with `REQUIRE_TEST_INFRA=1`  
- [ ] `flickr:install-indexes` + `flickr:doctor` on a real host  
- [ ] Register app + OAuth once end-to-end  
- [ ] Confirm OAuth callback throttle + HTTPS  
- [ ] CHANGELOG / README show `^1.0` only  

## 7. Change log of deep-dive remediations (this pass)

- Shared Docker image tags; no package-specific mongo:7/redis:7; tmpfs (no extra volumes)  
- Rate-limit connection identity = SHA-256(API key)  
- OAuth callback throttled  
- Photo id lookup uses `whereIn`  
- Job does not fire `FlickrCallFailed` for token/app not-found  
- Limiter snapshot map bounded  
