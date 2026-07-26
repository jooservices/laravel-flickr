# AI skills (package)

Canonical agent rules: [`AGENTS.md`](../../AGENTS.md).  
Human docs: [`docs/`](../../docs/).  
Release notes: [`CHANGELOG.md`](../../CHANGELOG.md).

## When changing this package

1. **Entry:** `FlickrService` or facade `Facades\Flickr` + namespace adapters (page-level only — never multi-page loops).
2. **Multi-app:** respect `connection($name)` / `flickr.default_connection`; tokens are `(app_name, nsid)`.
3. **Sync default;** queue only via explicit `$queued = true`. Default job is **not** unique; opt in with `unique: true` → `UniqueFlickrRequestJob`.
4. **Call path:** adapters / `call()` → `FlickrJobDispatcher` → `FlickrRequestJob` → **`FlickrCallService`** (business logic not in the job).
5. **Adapters:** register in `FlickrAdapterRegistry` only; `PersistFlickrData` no-ops unknown namespaces.
6. **Rate limits:** always via `LimitingFlickrTransport` in `FlickrClientFactory` — never sleep for limits. Status CLI: `flickr:rate-limit:status`.
7. **OAuth complete:** use `OAuthCompletionService` (not duplicated controller/CLI orchestration).
8. **Runtime settings:** `laravel-config` `flickr.*` (not env), except `oauth.callback_path`.
9. **Do not** expose `activities` / `logs` / `events` on `FlickrService`.
10. **Default `per_page`** only on list-style methods; approaching events are transition-once (cache-backed).
11. **Docs:** update `docs/`, `AGENTS.md`, `README.md`, and `CHANGELOG.md` when public surface changes.
12. **Quality gate:** `composer check` (Mongo + Redis; shared tags `mongo:8.3.4` + `redis:8.8.0-alpine`).

## Release (this repo)

- Production/default branch: `master`; integration branch: `develop`
- Feature work: branch from `develop`, then open a PR back to `develop`
- Release prep: `release/X.Y.Z` from green `develop`, then open a PR into `master`
- After green post-merge `master` CI: create annotated tag `vX.Y.Z`; wait for the release workflow, then back-merge `master` into `develop` through a PR
