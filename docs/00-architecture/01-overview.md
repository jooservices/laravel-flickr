# Architecture overview

## Position in the stack

```text
Host application
  multi-page walks, product UI, host-specific orchestration
        ↓
jooservices/laravel-flickr
  FlickrService (+ facade), adapters, FlickrCallService,
  FlickrRequestJob, OAuth, rate limits, Mongo models/repos, listeners
        ↓
jooservices/flickr
  OAuth 1.0a, transport, method services, DTOs
        ↓
jooservices/client
  HTTP client (Guzzle 7.10+ / 8)
```

## Responsibilities

| Layer | Owns |
|---|---|
| Host | Multi-page walks, product workflows, UI |
| laravel-flickr | Connection + account scope, one-call adapters, shared job + rate-limit middleware, apps/tokens/OAuth, optional persistence listeners |
| flickr SDK | HTTP signing surface, raw method wrappers |
| client | Transport, retries, fakes |

## Patterns used

| Pattern | Where |
|---|---|
| Factory | `FlickrClientFactory` |
| Decorator | Force-auth client / limiting transport |
| Adapter | `Photos`, `People`, `Contacts`, `Tags`, … |
| Registry | `FlickrAdapterRegistry` |
| Repository | Apps, tokens + Mongo persistence |
| DTO | Credentials, tokens, call outcomes, rate-limit status |
| Strategy | `RequestLimiterInterface`, config resolvers |
| Facade | `Facades\Flickr` → `FlickrService` |

## Non-goals

- Sleeping for rate limits
- Looping all pages inside adapters
- Exposing `->activities` / `->logs` / `->events` on `FlickrService`
- Host UI

## Multi-app identity

Developers never pass raw OAuth credentials into adapters. They:

1. Register a named Flickr API app (`flickr:app:add {name}`)
2. Optionally select it with `connection($name)` (default from `flickr.default_connection`)
3. Scope with `as($nsid)` (token must exist for that app) or `anonymous()`
4. Call an adapter method (or `call()` escape hatch)

Tokens are stored and resolved as `(app_name, nsid)`.

## Call path (summary)

```text
FlickrService / Facade
  → Adapter | call()
  → FlickrJobDispatcher
  → FlickrRequestJob (sync or queue)
  → FlickrCallService::execute
  → FlickrClientFactory + LimitingFlickrTransport
  → events → Log / Record / Persist
```
