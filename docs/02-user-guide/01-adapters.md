# Adapters

Entry point: `JOOservices\LaravelFlickr\Service\FlickrService`.

1. Optionally pick a Flickr API app with `connection($name)` (defaults to `flickr.default_connection`).
2. Scope with `as($nsid)` (token must exist for that connection) or `anonymous()`.
3. Access an adapter via magic property (`->contacts`, `->people`, …).
4. Call a method — one Flickr HTTP request per call.

```php
app(FlickrService::class)->as($nsid)->contacts->getList(['page' => 1, 'per_page' => 100]);
app(FlickrService::class)->connection('backup')->as($nsid)->photos->getInfo($id);
app(FlickrService::class)->anonymous()->people->getPublicPhotos($nsid, ['per_page' => 20]);
app(FlickrService::class)->as($nsid)->test->login();
```

## Method matrix (high level)

| Adapter | Methods | Default `per_page` | Persistence |
|---|---|---|---|
| Photos | `getPopular`, `search`, `getInfo`, `getSizes` | list methods only | — |
| People | `getPhotos`, `getPublicPhotos` | yes | photos |
| Contacts | `getList`, `getPublicList` | yes | own list only |
| Photosets | `getList`, `getInfo`, `getPhotos` | list/getPhotos | getPhotos → photos + groups |
| Galleries | `getList`, `getInfo`, `getPhotos` | list/getPhotos | getPhotos → photos + groups |
| Favorites | `getList` | yes | photos + favorites |
| Test | `login`, `echo`, `null` | no | — |
| Tags | `getListUser`, `getListUserPopular`, `getListUserRaw`, `getHotList`, `getListPhoto`, `getRelated` | no | — |

## Queue opt-in

Most adapter methods accept `$queued = false`. When `true`, work is pushed through `FlickrRequestJob` on the configured queue; sync remains the default (job middleware still applies).

## Escape hatch

Any Flickr method (including namespaces without a first-class adapter):

```php
app(FlickrService::class)->as($nsid)->call('photos', 'getInfo', ['photo_id' => $id]);
app(FlickrService::class)->as($nsid)->call('groups', 'getInfo', ['group_id' => $id]);
// Optional: queue uniqueness (60s) and correlation id for logs/events
app(FlickrService::class)->as($nsid)->call(
    'contacts',
    'getList',
    ['page' => 1],
    queued: true,
    unique: true,
    correlationId: $runId,
);
```

Unknown namespaces do **not** break persistence listeners after a successful call — they simply do not auto-persist.

## Not adapters

`activities`, `logs`, and `events` are **not** FlickrService properties. Use `ActivityLogService` / `StoredEventService` from the container.
