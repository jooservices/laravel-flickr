# AI skills (package)

Canonical agent rules live in [`AGENTS.md`](../../AGENTS.md). Full human docs live in [`docs/`](../../docs/).

When changing this package:

1. Entry point is `FlickrService` + namespace adapters (page-level calls only — never multi-page loops)
2. Multi-app: always respect `connection($name)` / `flickr.default_connection`; tokens are keyed by `(app_name, nsid)`
3. Sync by default; queue only via explicit `$queued = true` on adapter/`call` paths (`FlickrRequestJob`)
4. Rate limiting must stay wired through `LimitingFlickrTransport` in `FlickrClientFactory` — never sleep for limits
5. Runtime settings come from laravel-config `flickr.*` (not env), except `oauth.callback_path`
6. Do not expose `activities` / `logs` / `events` on `FlickrService`
7. Default `per_page` only on list-style methods; approaching events are transition-once
8. Run `composer check` (with Mongo + Redis) before claiming done
