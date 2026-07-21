# AGENTS.md — jooservices/laravel-flickr

## Core intent

- Laravel integration on top of `jooservices/flickr`.
- **Sync / raw request only.** No queues, jobs, Horizon, spider, or crawl-run state machine.
- Page-level fetch helpers only — hosts own multi-page walks and persistence.
- Principles: **SOLID, DRY, KISS, YAGNI**. Explicit over clever.
- Design patterns only when justified (Factory, Decorator for force-auth, thin fetch services).
- Host layering: `Controller → FormRequest → Service → Repository`; this package is called from Services.
- Follow Laravel naming and sibling JOOservices quality bars (`dto`, `flickr`, `laravel-identity`).

## Public surface

- `FlickrClientFactory` / `FlickrClientFactoryInterface`
- `AppCredentialsResolverInterface` + `ConfigCredentialsResolver`
- DTOs: `AppCredentials`, `OAuthToken`, `PageRequest`, `PagedResult`, `TokenHealthResult`
- Fetch: `ContactsFetcher`, `PeoplePhotosFetcher`, `PhotosetsFetcher`, `GalleriesFetcher`, `FavoritesFetcher`, `TokenHealthProbe`
- Config: `laravel-flickr.php`

## Hard rules

1. Never dispatch jobs or sleep for rate limits inside this package.
2. Never loop all pages of a Flickr list API.
3. Account-bound clients go through the factory (force-auth).
4. Anonymous probes use `anonymous()` / `anonymousFromConfig()` explicitly.
5. Do not add catalog Eloquent models or crawl tables.

## Quality

```bash
composer test
composer lint
composer lint:all
composer check
composer ci
```

Prefer fixing smells over suppressions.
