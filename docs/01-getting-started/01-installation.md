# Installation

```bash
composer require jooservices/laravel-flickr:^1.0
```

Requires:

- PHP 8.5+
- Laravel illuminate components `^13.0`
- MongoDB (apps, tokens, optional persistence)
- Redis (rate limiting and OAuth pending state)
- `jooservices/laravel-config` (runtime `flickr.*` settings)

## Publish package config

```bash
php artisan vendor:publish --tag=laravel-flickr-config
```

```env
FLICKR_OAUTH_CALLBACK_PATH=api/v1/oauth/flickr/callback
```

All other settings are stored in `jooservices/laravel-config` (group `flickr`). See [config reference](../02-user-guide/06-config-reference.md).

## First-time setup

```bash
# Ensure host MongoDB + Redis are configured
php artisan flickr:app:add default --api-key=your_key --api-secret=your_secret
php artisan flickr:install-indexes
php artisan flickr:oauth:authorize default
php artisan flickr:doctor
```

## Artisan commands

| Command | Purpose |
|---|---|
| `flickr:app:add {name}` | Register / update a Flickr API app |
| `flickr:oauth:authorize {connection?}` | Begin OAuth |
| `flickr:oauth:complete` | Finish OOB OAuth |
| `flickr:oauth:revoke` | Delete stored token |
| `flickr:install-indexes` | Create unique Mongo indexes |
| `flickr:doctor` | Read-only dependency check |

The service provider auto-registers via Composer `extra.laravel.providers`.
