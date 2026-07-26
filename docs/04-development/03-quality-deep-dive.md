# Quality deep dive (1.1.0)

Living checklist for code quality, performance, security, and documentation readiness.

## 1. Architecture quality

| Area | Assessment | Notes |
|---|---|---|
| SOLID | Strong | Factory, decorator transport, repository seams, resolver interfaces, call service |
| DRY | Strong | Shared job dispatcher, adapter registry, OAuth completion service, config helpers |
| KISS | Strong | Thin job shell; no crawl engine |
| YAGNI | Strong | Page-level only; no multi-page adapters |
| Patterns | Appropriate | Factory, Decorator, Adapter, Repository, DTO, Strategy |

### Hard rules (enforced)

1. Never sleep for rate limits  
2. Never multi-page loop in adapters  
3. Account-bound tokens resolved in `FlickrService::getClient()` and `FlickrCallService` (job only shells to the service)  
4. Anonymous requires registered app  
5. No `activities`/`logs`/`events` on `FlickrService`  
6. Default `per_page` only on list methods  
7. Approaching event fires on **transition** only (cache-backed)  
8. Unknown adapter namespaces in `PersistFlickrData` **no-op** (never throw after successful HTTP)

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
| Rate-limit Redis keys | SHA-256 of API key via `RateLimitConnectionKey` |
| Events (`FlickrOAuthCompleted`, etc.) | No bearer credentials |
| OAuth HTTP callback | FormRequest validation + `throttle:30,1` |
| Force-auth client | Authenticated REST forced for account-scoped clients |

### Host obligations

- Protect `APP_KEY`, MongoDB, and Redis  
- Use HTTPS for public OAuth callback  
- Isolate test Redis DB / Mongo database names  

### Residual risks (accepted / host-owned)

- Reflection into SDK private client field (`ForceAuthenticatedFlickr`) — SDK has no client decorator hook; fail-closed on unexpected types; covered by factory tests  
- Activity log may store Flickr exception **messages** (ops value; avoid logging full params with PII if hosts extend listeners)  
- API key still lives in process memory when building clients (unavoidable for signing)  

## 3. Performance

| Path | Design | Status |
|---|---|---|
| Rate-limit claim | Atomic Redis Lua (min-gap + hourly) | Good |
| Completed-call quota | In-memory acquire snapshot (`fresh: false`) | Good |
| Status probe reads | Pipeline after window prune | Good |
| Snapshot map | Capped at 64 keys per worker | Good |
| Photo membership | Pivot collections first, then `whereIn` photos | Good |
| Upsert page | Mongo `bulkWrite` via `MongoBulkUpsert` | Single bulk round-trip |
| Config | laravel-config memory cache (upstream) | Live without restart |
| Limiter disabled | Checked per acquire — no worker recycle | Good |

### Non-goals (deliberate)

- Multi-page crawls  
- Sleep/backoff inside package  
- Host-facing bulk Mongo write API  

## 4. Test posture

| Metric | Target | Approach |
|---|---|---|
| Real Mongo/Redis | Required for infra tests | Shared ecosystem images; dedicated DB/DB index |
| HTTP | Fake via client kit | Full factory → transport → job path |
| CI | Fail if infra missing | `REQUIRE_TEST_INFRA=1` |
| Coverage | High line coverage on `src/` | PHPUnit clover |

Suites include happy / unhappy / validation / multi-app / rate-limit transition / OAuth security paths / Tags / facade / rate-limit status command.

## 5. Documentation map

| Doc | Purpose |
|---|---|
| `00-architecture/*` | Stack, class map, flows, models |
| `01-getting-started/*` | Install + quick start |
| `02-user-guide/*` | Adapters, multi-app, OAuth, limits, events, config |
| `04-development/*` | Standards, testing (shared Docker), this deep dive |
| `AGENTS.md` | Canonical agent rules |
| `ai/skills/README.md` | Short agent checklist pointing at AGENTS + docs |

## 6. Ops checklist before tagging a release

- [ ] Host Mongo + Redis using shared tags (`mongo:8.3.4`, `redis:8.8.0-alpine`)  
- [ ] `composer lint:all && composer test` green with `REQUIRE_TEST_INFRA=1`  
- [ ] `flickr:install-indexes` + `flickr:doctor` on a real host  
- [ ] `CHANGELOG.md` has the version section (not only Unreleased)  
- [ ] README version / badges / require constraint match the tag  
- [ ] Merge `release/X.Y.Z` from `develop` into `master` only after protected CI passes
- [ ] Wait for post-merge `master` CI, then create annotated tag `vX.Y.Z`; verify the release workflow and back-merge into `develop`
