# Installation

```bash
composer require jooservices/laravel-flickr
```

Requires PHP 8.5+ and Laravel illuminate components 11–13.

```bash
php artisan vendor:publish --tag=laravel-flickr-config
```

```env
FLICKR_API_KEY=your_key
FLICKR_API_SECRET=your_secret
FLICKR_DEFAULT_PER_PAGE=100
```

The service provider auto-registers via Composer `extra.laravel.providers`.
