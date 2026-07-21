# OAuth and rate limiting — v1.1.0

`jooservices/laravel-flickr` provides synchronous OAuth 1.0a and optional request-limiting building blocks for Laravel hosts.

## Included

- `OAuthService::begin()` and `complete()` using explicit `AppCredentials`.
- `OAuthBeginResult` and existing `OAuthToken` DTOs.
- A required Flickr NSID when completing OAuth.
- `RequestLimiterInterface`, `Permit`, `DenyReason`, and `NullRequestLimiter`.
- `LimitingFlickrTransport`, which denies locally before I/O and triggers a cooldown after HTTP 429.

## Host responsibilities

Hosts own callback/session validation, request-token-secret storage, encrypted access-token persistence, Redis limiter binding, retry policy, telemetry, and user interface behavior.

## Constraints

- No queue dispatching, sleeping, multi-page loops, Eloquent models, migrations, or UI.
- OAuth tokens, verifiers, and API secrets must never be logged.
- The transport throws `RateLimitedException`; hosts choose retry or queue behavior.
