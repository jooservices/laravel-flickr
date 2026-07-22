# Multi Flickr API apps

A **connection** is a named Flickr API application (key + secret) stored in MongoDB.

## Why

- Rotate or stage credentials without redeploying env files
- Separate production / backup / experimental Flickr apps
- Rate-limit buckets follow the app’s API key

## Register

```bash
php artisan flickr:app:add default --api-key=… --api-secret=…
php artisan flickr:app:add backup --api-key=… --api-secret=…
```

Default connection name comes from laravel-config:

```text
flickr.default_connection = default
```

## Use in code

```php
// Default connection
app(FlickrService::class)->as($nsid)->photos->getInfo($id);

// Named connection
app(FlickrService::class)
    ->connection('backup')
    ->as($nsid)
    ->photos
    ->getInfo($id);
```

## Tokens

OAuth tokens are stored per **(app_name, nsid)**. Authorizing `user@N00` on `default` does not create a token for `backup`. Re-run OAuth for each connection that needs the account.

```bash
php artisan flickr:oauth:authorize default
php artisan flickr:oauth:authorize backup
```

Deleting an app (`AppRepository::forget`) cascades token deletion for that app name.
