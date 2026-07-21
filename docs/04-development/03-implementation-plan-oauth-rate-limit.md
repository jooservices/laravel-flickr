# Implementation plan: OAuth + rate limit in `laravel-flickr`

**Status:** Ready for handover (planning only — not implemented beyond v1.0.0)  
**Package:** `jooservices/laravel-flickr`  
**Repo:** https://github.com/jooservices/laravel-flickr  
**Related consumer:** XFlickr (`/Users/vietvu/Sites/JOOservices/XFlickr`)  
**Date:** 2026-07-21  
**Audience:** Developer taking over package evolution + optional XFlickr adoption  

---

## 1. Goal

Expand `laravel-flickr` so it can power a **standalone Laravel app** that:

1. Configures Flickr app credentials  
2. Completes OAuth 1.0a connect  
3. Builds account-bound clients  
4. Fetches pages of data (contacts, photos, …)  
5. Optionally rate-limits requests  

…**without** requiring spider, crawl jobs, catalog DB, or XFlickr modules.

**Non-goals (explicit):**

- No Horizon / queue jobs in this package  
- No crawl run / target state machine  
- No spider frontier  
- No required Eloquent catalog models  
- No product UI (Blade/Inertia)  

XFlickr continues to own queues, crawl, spider, Transfer, and product HTTP shells.

---

## 2. Current state (v1.0.0)

### Package already has

| Area | Location |
|------|----------|
| Client factory (authenticated force-auth + anonymous) | `src/Client/FlickrClientFactory.php` |
| Force-auth decorator | `src/Client/ForceAuthenticatedFlickr*.php` |
| Page fetchers | `src/Fetch/*` |
| Token health probe | `src/Fetch/TokenHealthProbe.php` |
| Config credentials resolver | `src/Support/ConfigCredentialsResolver.php` |
| DTOs | `AppCredentials`, `OAuthToken`, `PageRequest`, `PagedResult`, `TokenHealthResult` |

### Package does **not** have

- OAuth begin/complete flow  
- Rate limiter  
- Transport decorator combining limiter + HTTP  
- Default Connection persistence  

### XFlickr already has (consumer reference)

Use as **behavior reference**, not code to copy blindly:

| Concern | XFlickr location |
|---------|------------------|
| OAuth begin/complete | `Modules/Flickr/app/Services/FlickrOAuthService.php` |
| Account client + observing transport | `Modules/Flickr/app/Client/FlickrAccountClientFactory.php`, `ObservingFlickrTransport.php` |
| Rate limiter (Lua Redis) | `Modules/Flickr/app/Services/FlickrRequestLimiter.php` |
| Outcome classifier / ApiLog | `FlickrApiOutcomeClassifier`, `FlickrApiTelemetryService` |
| Connection model / activate-disconnect | `Connection`, `ConnectionRegistryService` |
| Package wiring | Factory already calls `Jooservices\LaravelFlickr\Client\FlickrClientFactory` |

**Important:** XFlickr OAuth still uses SDK `FlickrFactory` directly (pre-connection). That is the main candidate to move into the package.

---

## 3. Target architecture

```text
┌─────────────────────────────────────────────────────────────┐
│ Host app (minimal OR XFlickr)                               │
│  - HTTP / session / UI                                      │
│  - Token persistence (host repository)                      │
│  - Jobs / crawl / spider (XFlickr only)                     │
└───────────────────────────┬─────────────────────────────────┘
                            │
┌───────────────────────────▼─────────────────────────────────┐
│ jooservices/laravel-flickr                                  │
│  - OAuthService (begin / complete)     [NEW]                │
│  - FlickrClientFactory                 [EXISTS]             │
│  - RequestLimiter (sync acquire)       [NEW]                │
│  - Fetch helpers + TokenHealthProbe    [EXISTS]             │
│  - Optional: LimitingTransport decorator [OPTIONAL LATER]   │
└───────────────────────────┬─────────────────────────────────┘
                            │
┌───────────────────────────▼─────────────────────────────────┐
│ jooservices/flickr (SDK)                                    │
│  - FlickrFactory, OAuth1, transport, method services        │
└─────────────────────────────────────────────────────────────┘
```

### Layering rules for implementers

1. **SOLID / KISS / YAGNI** — no interface-per-class spam; interfaces only at host-replaceable seams.  
2. **Sync only** — package methods never `dispatch()` jobs or `sleep()` for backoff.  
3. **Page-level only** — fetch helpers never loop all pages.  
4. **No `App\` imports** — package must not depend on any host app.  
5. Host flow remains: `Controller → FormRequest → Service → package → (host Repository)`.

---

## 4. Phased delivery

### Phase A — OAuth service (package v1.1.0) — **do first**

**Why first:** Unblocks standalone “connect Flickr” apps; small surface; mirrors existing SDK auth APIs.

#### A.1 Public API (proposed)

```php
namespace Jooservices\LaravelFlickr\OAuth;

final class OAuthService
{
    public function __construct(
        private readonly ?FlickrTransportContract $transport = null,
    ) {}

    /**
     * @return OAuthBeginResult { authorizationUrl, oauthToken, oauthTokenSecret, appCredentials fingerprint optional }
     */
    public function begin(
        AppCredentials $credentials,
        AuthPermission $permission = AuthPermission::Read,
        ?string $callbackUrl = null, // if SDK/config needs override
    ): OAuthBeginResult;

    /**
     * Exchange verifier for access token.
     *
     * @throws AuthenticationException on Flickr failure
     * @throws InvalidArgumentException if NSID missing (recommended hard rule)
     */
    public function complete(
        AppCredentials $credentials,
        string $oauthToken,
        string $oauthVerifier,
        string $oauthTokenSecret,
    ): OAuthToken; // reuse existing Dto\OAuthToken (user access token)
}
```

**New DTOs (suggested):**

| DTO | Fields |
|-----|--------|
| `OAuthBeginResult` | `authorizationUrl: string`, `requestToken: string`, `requestTokenSecret: string` |
| (existing) `OAuthToken` | access token + secret + optional nsid/username/fullname |

**Behavior rules (copy from XFlickr product decisions):**

1. Prefer Read permission unless host passes write (document).  
2. **Require NSID** on complete — do not invent `unknown` connection keys.  
3. Never log raw oauth tokens; fingerprints only if logging is added later.  
4. No session reads/writes inside package — host stores request token secret between begin and complete.  
5. No Eloquent — return DTOs only.

#### A.2 Implementation notes

- Build temporary SDK client via `FlickrFactory::make` / existing package factory patterns for **app credentials only** (no user token store for begin).  
- Use SDK `auth()->requestToken()`, `authorizationUrl()`, `accessToken()`.  
- Map SDK `AccessTokenData` → `OAuthToken`.  
- Keep `FlickrClientFactory` as sole place for **post-connect** clients.

#### A.3 Tests (package)

| Test | Assert |
|------|--------|
| `OAuthServiceTest::begin_returns_url_and_request_tokens` | Fake transport returns request token payload |
| `complete_returns_oauth_token_with_nsid` | Access token mapped |
| `complete_rejects_missing_nsid` | Exception |
| `complete_propagates_auth_failure` | SDK exception type preserved or wrapped once |

Use `JOOservices\Flickr\Client\FakeFlickrTransport` like existing tests.

#### A.4 Docs (package)

- Update `README.md` with OAuth connect snippet  
- `docs/01-getting-started/03-oauth-connect.md`  
- `docs/02-user-guide/02-oauth.md`  
- `CHANGELOG.md` under Unreleased → release as **1.1.0**  
- Update `AGENTS.md` hard rules (OAuth in package; still no queues)

#### A.5 XFlickr adoption (separate PR, after package release)

| Task | Detail |
|------|--------|
| Bump require | `"jooservices/laravel-flickr": "^1.1"` |
| Refactor `FlickrOAuthService` | Delegate begin/complete body to package `OAuthService` |
| Keep host-only logic | Session, activate/disconnect, events, multi-profile `FlickrAppConfig`, ActivityLog |
| Architecture test | `FlickrClientFactoryLayeringTest` — OAuth service may stop using `FlickrFactory` if package owns it; allowlist only package vendor code |
| Tests | Existing `FlickrOAuthServiceTest` / `FlickrAuthControllerTest` stay green |

**Do not** move routes, controllers, or Connection registry into the package in Phase A.

---

### Phase B — Rate limiter (package v1.2.0)

**Why second:** Independent of OAuth; XFlickr already has a mature Redis Lua implementation to port carefully.

#### B.1 Public API (proposed)

```php
namespace Jooservices\LaravelFlickr\RateLimit;

enum DenyReason: string {
    case GlobalPause = 'global_pause';
    case Cooldown = 'cooldown';
    case MinGap = 'min_gap';
    case HourlyQuota = 'hourly_quota';
}

final readonly class Permit {
    public function __construct(
        public bool $acquired,
        public int $retryAfterSeconds,
        public ?DenyReason $reason = null,
        public ?\Carbon\CarbonImmutable $acquiredAt = null,
    ) {}
}

interface RequestLimiterInterface {
    public function acquire(string $connectionKey): Permit;
    public function triggerCooldown(string $connectionKey, ?int $seconds = null): \Carbon\CarbonImmutable;
    public function state(string $connectionKey): LimiterState; // DTO for remaining/used/pause
}

final class RedisRequestLimiter implements RequestLimiterInterface { ... }
```

#### B.2 Configuration (package config keys)

Extend `config/laravel-flickr.php`:

```php
'rate_limit' => [
    'enabled' => env('FLICKR_RATE_LIMIT_ENABLED', true),
    'min_gap_ms' => (int) env('FLICKR_RATE_LIMIT_MIN_GAP_MS', 333),
    'window_seconds' => (int) env('FLICKR_RATE_LIMIT_WINDOW_SECONDS', 3600),
    'max_requests_per_hour' => (int) env('FLICKR_RATE_LIMIT_MAX_PER_HOUR', 3500),
    'cooldown_seconds' => (int) env('FLICKR_RATE_LIMIT_COOLDOWN_SECONDS', 3600),
    'key_prefix' => env('FLICKR_RATE_LIMIT_KEY_PREFIX', 'laravel-flickr:req'),
],
```

**Redis key design:** document stable prefix; allow prefix override so XFlickr can migrate without clashing or can dual-run.

#### B.3 Port from XFlickr carefully

Reference: `Modules/Flickr/app/Services/FlickrRequestLimiter.php`

| Port | Skip (XFlickr-only) |
|------|---------------------|
| Min-gap Lua claim | ActivityLog on cooldown/deny |
| Hourly ZSET Lua claim | `FlickrCallerMode` product policy |
| Cooldown SETEX | Global pause via XFlickr Settings Mongo key (make injectable) |
| `state()` for meters | Catalog count joins on rate-limit snapshot UI |

**Global pause:** inject a `callable|PauseResolverInterface` defaulting to `false`, so XFlickr can bind `xflickr.global_pause` without package reading Mongo.

#### B.4 Optional transport decorator (B.4 — can be B.2.1 or v1.2.1)

```php
final class LimitingFlickrTransport implements FlickrTransportContract
{
    // acquire → inner->request → on 429 triggerCooldown + throw package RateLimitedException
}
```

**Rules:**

- Do **not** write ApiLog (host/XFlickr keeps telemetry).  
- Do **not** sleep.  
- Throw `Jooservices\LaravelFlickr\Exceptions\RateLimitedException` with `retryAfterSeconds`.  

XFlickr can keep `ObservingFlickrTransport` longer (limiter + cache + telemetry) and only swap limiter guts to package interface first (lower risk than full transport replace).

#### B.5 Tests (package)

| Test | Assert |
|------|--------|
| acquire allows first request | acquired true |
| min-gap deny | reason MinGap, retryAfter >= 1 |
| hourly quota deny | reason HourlyQuota |
| cooldown deny after triggerCooldown | reason Cooldown |
| state remaining decreases | used/remaining |
| disabled limiter always allows | config enabled false |

Use Orchestra + Redis if available; otherwise skip with `markTestSkipped` when Redis down (document). Prefer phpredis/predis already pulled transitively by illuminate if possible — **explicitly require** `illuminate/redis` or document host must install Redis for limiter.

**Composer:** add `"illuminate/redis": "^11|^12|^13"` only if limiter always registered; or make limiter optional so default install stays light.

**Recommendation:** optional binding — if Redis not configured, bind `NullRequestLimiter` (always acquire). Document.

#### B.6 XFlickr adoption (after 1.2.0)

| Step | Detail |
|------|--------|
| Implement `RequestLimiterInterface` adapter **or** replace body of `FlickrRequestLimiter` with package Redis limiter | Prefer adapter first |
| Keep XFlickr ActivityLog side effects in adapter/wrapper | Package stays pure |
| Keep ApiLog / ObservingFlickrTransport | Unchanged initially |
| Align Redis key prefix or migrate keys | Write migration note in CHANGELOG |
| Regression tests | `FlickrRequestLimiter*Test`, crawl job rate-limit tests |

---

### Phase C — Optional “simple host” persistence (v1.3.0, YAGNI gate)

Only if a second product needs drop-in tables:

- Publishable migration `flickr_connections`  
- Optional Eloquent model + repository interface  
- XFlickr **must not** be forced to use it  

**Skip until** a concrete second consumer asks for it.

---

### Phase D — Never in this package (record for handover)

| Feature | Owner |
|---------|--------|
| Horizon jobs / `FetchCrawlPageJob` | XFlickr Crawler |
| Crawl runs/targets/catalog upserts | XFlickr Crawler |
| Spider planner / frontier | XFlickr Spider |
| ActivityLog / Operations panels | XFlickr |
| Multi-profile Settings UI | XFlickr Settings |
| Transfer download orchestration | XFlickr Transfer |

If crawl is ever extracted: new package name e.g. `jooservices/laravel-flickr-crawler` — **not** bloating `laravel-flickr`.

---

## 5. Work breakdown (tickets for implementer)

### Package repo tickets

| ID | Title | Phase | Estimate | Depends |
|----|-------|-------|----------|---------|
| P-A1 | Add `OAuthBeginResult` + `OAuthService` + unit tests | A | M | — |
| P-A2 | Docs + README OAuth quickstart | A | S | P-A1 |
| P-A3 | Release `v1.1.0` (tag + CI green) | A | S | P-A1, P-A2 |
| P-B1 | Add `Permit`, `DenyReason`, `LimiterState`, interface | B | S | — |
| P-B2 | Implement `RedisRequestLimiter` (+ Null limiter) | B | L | P-B1 |
| P-B3 | Config keys + service provider bindings | B | S | P-B2 |
| P-B4 | Unit tests (Redis) | B | M | P-B2 |
| P-B5 | Docs rate limit + CHANGELOG | B | S | P-B2 |
| P-B6 | Release `v1.2.0` | B | S | P-B2–B5 |
| P-B7 | (Optional) `LimitingFlickrTransport` | B+ | M | P-B6 |

### XFlickr tickets (consumer)

| ID | Title | Phase | Estimate | Depends |
|----|-------|-------|----------|---------|
| X-A1 | Require `laravel-flickr:^1.1` | A | S | P-A3 |
| X-A2 | Delegate `FlickrOAuthService` begin/complete to package | A | M | X-A1 |
| X-A3 | Keep session/events/activate; tests green | A | M | X-A2 |
| X-B1 | Require `^1.2`; wrap/replace rate limiter | B | L | P-B6 |
| X-B2 | Preserve ActivityLog + ObservingFlickrTransport behavior | B | M | X-B1 |
| X-B3 | Gate: `bash scripts/test.sh gate` | B | S | X-B2 |

**Note:** XFlickr already depends on `laravel-flickr:^1.0` and builds clients via package factory (as of integration PR). Do not regress that.

---

## 6. Detailed design constraints

### 6.1 OAuth security

- Never log `oauth_token`, `oauth_token_secret`, `oauth_verifier` in full.  
- Host must use HTTPS callbacks in production.  
- Validate callback `oauth_token` matches session request token (host responsibility — document in package README).  
- Encrypted storage of access tokens is **host** responsibility (XFlickr uses Eloquent `encrypted` cast).

### 6.2 Rate limit semantics (match XFlickr where possible)

Order of checks in `acquire()` (recommended same as XFlickr):

1. Global pause (resolver) → deny  
2. Cooldown key → deny  
3. Min-gap Lua → deny or claim  
4. Hourly window Lua → deny or claim  
5. Allow  

On remote HTTP 429 (if transport decorator exists): `triggerCooldown()` then throw.

### 6.3 Exception taxonomy

| Exception | When |
|-----------|------|
| `MissingCredentialsException` | Exists — empty app key/secret |
| `AuthenticationException` | Prefer rethrow SDK auth errors |
| `RateLimitedException` (new) | Local deny or remote 429 after cooldown |
| `InvalidArgumentException` | Missing NSID on OAuth complete |

Do not create a deep exception hierarchy without need.

### 6.4 Service provider registration

```php
// register()
$this->app->singleton(OAuthService::class);
$this->app->singleton(RequestLimiterInterface::class, function ($app) {
    if (! config('laravel-flickr.rate_limit.enabled')) {
        return new NullRequestLimiter();
    }
    return $app->make(RedisRequestLimiter::class);
});
```

Avoid hard-failing install when Redis is absent.

---

## 7. Quality gates (package)

Same as v1.0.0 — **all must pass before tag:**

```bash
composer lint:all   # pint, phpcs, phpstan, phpmd
composer test
composer check
composer ci         # coverage clover
```

CI workflows already exist:

- `.github/workflows/ci.yml` on push/PR  
- `.github/workflows/release.yml` on `v*.*.*` tags  

Release process:

1. CHANGELOG section for version  
2. Commit on `main`  
3. `git tag -a v1.1.0 -m "v1.1.0"` && push tag  
4. Confirm GitHub Release + CI green  
5. Packagist auto-update (or manual update)  
6. Bump consumer XFlickr  

Author/committer for JOOservices packages: follow org convention (XFlickr uses `Viet Vu <jooservices@gmail.com>`).

---

## 8. Acceptance criteria

### Phase A done when

- [ ] Standalone sample in README works with FakeFlickrTransport tests  
- [ ] `OAuthService::begin` / `complete` covered by unit tests  
- [ ] Missing NSID rejected  
- [ ] No session/Eloquent in package  
- [ ] Tagged `v1.1.0`, CI green, Packagist has 1.1.0  
- [ ] XFlickr optional follow-up: OAuth service delegates to package; full gate green  

### Phase B done when

- [ ] Limiter acquire/deny/cooldown/state tested  
- [ ] Null limiter when disabled  
- [ ] No ActivityLog / job dispatch in package  
- [ ] Tagged `v1.2.0`, CI green  
- [ ] XFlickr can run crawls with package limiter behind existing transport (behavior parity tests)  

---

## 9. Risks and mitigations

| Risk | Mitigation |
|------|------------|
| Redis key clash with XFlickr | Configurable prefix; dual-write period optional |
| Behavior drift vs XFlickr OAuth edge cases | Port tests from `FlickrOAuthServiceTest` |
| Package becomes crawl engine | Charter review; reject jobs PRs |
| Optional Redis dependency weight | Null limiter; soft optional illuminate/redis |
| Force-auth double-wrap | Package already wraps; XFlickr must not re-wrap (already removed local ForceAuthenticated*) |
| Global pause coupling | Inject pause resolver; default false |

---

## 10. Handover checklist (start of work)

1. Clone `jooservices/laravel-flickr`, run `composer install && composer check`.  
2. Read:  
   - `README.md`, `AGENTS.md`  
   - This plan  
   - XFlickr `FlickrOAuthService`, `FlickrRequestLimiter`, `ObservingFlickrTransport`  
   - XFlickr `docs/05-maintenance/flickr-client-factory-layering.md`  
3. Confirm Packagist `jooservices/laravel-flickr` versions.  
4. Implement Phase A only first; open PR; do not mix rate limit into same PR unless tiny.  
5. After Packagist release, consumer PR on XFlickr with `bash scripts/test.sh gate`.  

---

## 11. Suggested first PR scope (minimal viable)

**PR title:** `feat(oauth): add OAuthService begin/complete (v1.1.0)`

**Files to add:**

- `src/OAuth/OAuthService.php`  
- `src/Dto/OAuthBeginResult.php`  
- `tests/Unit/OAuth/OAuthServiceTest.php`  
- docs + CHANGELOG  

**Files to touch:**

- `src/LaravelFlickrServiceProvider.php` (singleton bind)  
- `README.md`, `AGENTS.md`  

**Out of PR:** rate limiter, transport decorator, XFlickr changes (follow-up).

---

## 12. Glossary

| Term | Meaning |
|------|---------|
| Force-auth | Every REST call OAuth-signed for connection clients |
| Page helper | Single-page Flickr list API; host loops pages |
| Observing transport | XFlickr-only: cache + permit + telemetry + cooldown |
| Connection key | Usually Flickr NSID; host identity for limiter buckets |

---

## 13. Decision log (do not re-litigate without new evidence)

| Decision | Choice | Rationale |
|----------|--------|-----------|
| OAuth in package | Yes | Standalone connect without spider |
| Rate limit in package | Yes, sync only | Provider safety |
| Jobs/queues in package | No | Product crawl engine; separate package if ever needed |
| Drop XFlickr `Modules/Flickr` | No | Product HTTP, Connection, telemetry, Transfer glue remain |
| Spider in package | No | XFlickr-only |

---

*End of plan. Implement Phase A first; treat this document as the source of truth for scope disputes.*
