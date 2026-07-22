# Security Policy

## Supported versions

| Version | Supported |
|---|---|
| 1.x | Yes |

## Reporting a vulnerability

Report security issues privately to **admin@jooservices.com**. Do not open public issues for undisclosed vulnerabilities.

## Secrets handling

- Never commit Flickr API keys, secrets, or OAuth tokens.
- This package encrypts at rest (Laravel `encrypted` cast via `APP_KEY`):
  - `flickr_apps.api_key` / `api_secret`
  - `flickr_tokens.oauth_token` / `oauth_token_secret`
- OAuth pending request-token secrets in Redis are encrypted with `Crypt` before storage.
- Rate-limit Redis keys and limiter events use `SHA-256(api_key)`, never the raw key.
- Public events omit bearer credentials (e.g. `FlickrOAuthCompleted` carries nsid/username only).
- Job payloads carry `appName` + `nsid`, never OAuth secrets.
- OAuth HTTP callback is throttled (`30/minute` by default).
- Hosts must protect `APP_KEY`, MongoDB, and Redis access.
