# Quick start

```php
use JOOservices\LaravelFlickr\Facades\Flickr;
use JOOservices\LaravelFlickr\Service\FlickrService;

// Register a Flickr API app first: php artisan flickr:app:add default --api-key=… --api-secret=…
// Then authorize: php artisan flickr:oauth:authorize default

$response = app(FlickrService::class)
    ->as($nsid)
    ->contacts
    ->getList(['per_page' => 100]);

// Or facade:
// Flickr::as($nsid)->contacts->getList(['per_page' => 100]);

if (! $response?->ok) {
    // handle Flickr error payload
    return;
}

foreach ($response->data['contacts']['contact'] ?? [] as $contact) {
    // persist in host repository / schedule next page
}
```

Named connection (second Flickr API app):

```php
app(FlickrService::class)
    ->connection('backup')
    ->as($nsid)
    ->photos
    ->getInfo($photoId);
```

Anonymous public probe:

```php
app(FlickrService::class)
    ->anonymous()
    ->people
    ->getPublicPhotos($ownerNsid, ['per_page' => 5]);
```

Tags (no Mongo persistence):

```php
app(FlickrService::class)
    ->as($nsid)
    ->tags
    ->getHotList(['period' => 'week', 'count' => 20]);
```

Queue a call (observe completion via events):

```php
app(FlickrService::class)
    ->as($nsid)
    ->contacts
    ->getList(['page' => 1], queued: true);

// Escape hatch with optional uniqueness + correlation id
app(FlickrService::class)->as($nsid)->call(
    'contacts',
    'getList',
    ['page' => 1],
    queued: true,
    unique: true,
    correlationId: $runId,
);
```

Do **not** use `FlickrService` for activity logs or stored events — resolve:

```php
app(\JOOservices\LaravelFlickr\Service\ActivityLogService::class);
app(\JOOservices\LaravelFlickr\Service\StoredEventService::class);
```
