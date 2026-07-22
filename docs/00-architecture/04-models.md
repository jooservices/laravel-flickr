# Models & collections

All models use MongoDB via `mongodb/laravel-mongodb` (`connection = mongodb`).

## `flickr_apps` — `Models\App`

| Field | Type | Notes |
|---|---|---|
| `name` | string | Unique connection name |
| `api_key` | string | **encrypted** cast |
| `api_secret` | string | **encrypted** cast |

DTO: `FlickrApp` / `AppCredentials`. Repository: `AppRepository` (`exists`, `find`, `store`, `forget` cascades tokens).

## `flickr_tokens` — `Models\Token`

| Field | Type | Notes |
|---|---|---|
| `app_name` | string | Connection name |
| `nsid` | string | Flickr user NSID |
| `oauth_token` | string | **encrypted** |
| `oauth_token_secret` | string | **encrypted** |
| `username` | string? | |
| `fullname` | string? | |

Unique: `(app_name, nsid)`. DTO: `OAuthToken`. Repository: `TokenRepository`.

## `flickr_contacts` — `Models\Contact`

| Field | Notes |
|---|---|
| `owner_nsid` | Scoped account |
| `contact_nsid` | Contact identity |
| `raw` | Last seen Flickr payload |
| `last_seen_at` / `removed_at` | Reconciliation |

Unique: `(owner_nsid, contact_nsid)`.

## `flickr_photos` — `Models\Photo`

| Field | Notes |
|---|---|
| `owner_nsid` | Account that fetched / owns context |
| `photo_id` | Flickr photo id |
| `raw` | Payload |
| `last_seen_at` / `removed_at` | Reconciliation |

Unique: `(owner_nsid, photo_id)`.

## `flickr_photo_groups` — `Models\PhotoGroup`

Pivot for photoset/gallery membership.

| Field | Notes |
|---|---|
| `owner_nsid` | |
| `group_type` | `photoset` \| `gallery` |
| `group_id` | Flickr photoset/gallery id |
| `photo_id` | |

Unique: `(owner_nsid, group_type, group_id, photo_id)`.

## `flickr_photo_favorites` — `Models\PhotoFavorite`

| Field | Notes |
|---|---|
| `owner_nsid` | |
| `photo_id` | |

Unique: `(owner_nsid, photo_id)`.

## Indexes

Install with:

```bash
php artisan flickr:install-indexes
```

Creates unique indexes matching the tables above. `flickr:doctor` verifies index expectations.

## Upstream collections

Activity logs and stored events use models from `jooservices/laravel-logging` and `jooservices/laravel-events` (not owned by this package). Thin read wrappers: `ActivityLogService`, `StoredEventService`.
