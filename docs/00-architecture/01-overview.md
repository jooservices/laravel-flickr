# Architecture overview

## Position in the stack

```text
Host application
  queues, spider, catalog DB, UI
        ↓
jooservices/laravel-flickr
  factory, force-auth, page fetch helpers
        ↓
jooservices/flickr
  OAuth 1.0a, transport, method services, DTOs
```

## Responsibilities

| Layer | Owns |
|---|---|
| Host | Credentials storage encryption, multi-page walks, jobs, spider, catalog writes |
| laravel-flickr | Account-bound client construction, page-level Flickr calls, normalized `PagedResult` |
| flickr SDK | HTTP, signing, raw method wrappers |

## Patterns used

| Pattern | Where |
|---|---|
| Factory | `FlickrClientFactory` |
| Decorator | `ForceAuthenticatedFlickrClient` |
| Strategy / SRP services | Per-domain fetchers |
| DTO | Credentials, tokens, page results |

## Non-goals

- Horizon / queues
- Crawl run / target tables
- Spider frontier
- Eloquent catalog models
