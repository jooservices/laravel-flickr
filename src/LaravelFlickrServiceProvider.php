<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr;

use Illuminate\Support\ServiceProvider;
use Jooservices\LaravelFlickr\Client\FlickrClientFactory;
use Jooservices\LaravelFlickr\Contracts\AppCredentialsResolverInterface;
use Jooservices\LaravelFlickr\Contracts\FlickrClientFactoryInterface;
use Jooservices\LaravelFlickr\Fetch\ContactsFetcher;
use Jooservices\LaravelFlickr\Fetch\FavoritesFetcher;
use Jooservices\LaravelFlickr\Fetch\GalleriesFetcher;
use Jooservices\LaravelFlickr\Fetch\PeoplePhotosFetcher;
use Jooservices\LaravelFlickr\Fetch\PhotosetsFetcher;
use Jooservices\LaravelFlickr\Fetch\TokenHealthProbe;
use Jooservices\LaravelFlickr\OAuth\OAuthService;
use Jooservices\LaravelFlickr\RateLimit\NullRequestLimiter;
use Jooservices\LaravelFlickr\RateLimit\RedisRequestLimiter;
use Jooservices\LaravelFlickr\RateLimit\RequestLimiterInterface;
use Jooservices\LaravelFlickr\Support\ConfigCredentialsResolver;
use Jooservices\LaravelFlickr\Support\QueryDefaults;

final class LaravelFlickrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-flickr.php', 'laravel-flickr');

        $this->app->singleton(AppCredentialsResolverInterface::class, ConfigCredentialsResolver::class);
        $this->app->singleton(QueryDefaults::class);
        $this->app->singleton(FlickrClientFactory::class);
        $this->app->singleton(OAuthService::class);
        $this->app->singleton(
            RequestLimiterInterface::class,
            fn (): RequestLimiterInterface => filter_var(
                config('laravel-flickr.rate_limit.enabled', true),
                FILTER_VALIDATE_BOOL,
            )
                ? new RedisRequestLimiter()
                : new NullRequestLimiter(),
        );
        $this->app->bind(FlickrClientFactoryInterface::class, FlickrClientFactory::class);

        $this->app->singleton(ContactsFetcher::class);
        $this->app->singleton(PeoplePhotosFetcher::class);
        $this->app->singleton(PhotosetsFetcher::class);
        $this->app->singleton(GalleriesFetcher::class);
        $this->app->singleton(FavoritesFetcher::class);
        $this->app->singleton(TokenHealthProbe::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laravel-flickr.php' => config_path('laravel-flickr.php'),
            ], 'laravel-flickr-config');
        }
    }
}
