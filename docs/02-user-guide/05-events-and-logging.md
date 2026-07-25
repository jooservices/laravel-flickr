# Events and logging

## Package events

| Event | Purpose |
|---|---|
| `FlickrCallStarting` | `FlickrCallService::execute` entered (optional `correlationId`) |
| `FlickrCallCompleted` | HTTP (or SDK) call finished; carries `FlickrCallOutcome` |
| `FlickrCallFailed` | Throwable during call (rethrown after event); richer context + optional correlation id |
| `FlickrRateLimited` | Local limiter denial |
| `FlickrRateLimitApproaching` | Quota warning (transition) |
| `FlickrClientResolved` | Raw SDK client handed out via `getClient()` |
| `FlickrOAuthCompleted` | Token stored |
| `FlickrOAuthRevoked` | Token deleted |
| `FlickrContactRemoved` / `FlickrPhotoRemoved` / `FlickrPhotoGroupRemoved` / `FlickrPhotoUnfavorited` | Reconciliation removals |

## Listeners

| Listener | Role | Gated by |
|---|---|---|
| `LogFlickrActivity` | Writes activity via laravel-logging | `flickr.logging_enabled` |
| `RecordFlickrEvent` | Stores events via laravel-events | `flickr.events_enabled` |
| `PersistFlickrData` | Upserts photos/contacts/groups/favorites | Adapter `PersistsResults` |

## Read services (not on FlickrService)

```php
app(\JOOservices\LaravelFlickr\Service\ActivityLogService::class);
app(\JOOservices\LaravelFlickr\Service\StoredEventService::class);
```

For advanced queries, use upstream package APIs directly.
