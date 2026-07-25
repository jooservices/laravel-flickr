<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use JOOservices\LaravelFlickr\Client\FlickrClientFactory;
use JOOservices\LaravelFlickr\Console\Commands\FlickrAppAddCommand;
use JOOservices\LaravelFlickr\Console\Commands\FlickrDoctorCommand;
use JOOservices\LaravelFlickr\Console\Commands\FlickrInstallIndexesCommand;
use JOOservices\LaravelFlickr\Console\Commands\FlickrOAuthAuthorizeCommand;
use JOOservices\LaravelFlickr\Console\Commands\FlickrOAuthCompleteCommand;
use JOOservices\LaravelFlickr\Console\Commands\FlickrOAuthRevokeCommand;
use JOOservices\LaravelFlickr\Console\Commands\FlickrRateLimitStatusCommand;
use JOOservices\LaravelFlickr\Contracts\FlickrClientFactoryInterface;
use JOOservices\LaravelFlickr\Contracts\RateLimitConfigResolverInterface;
use JOOservices\LaravelFlickr\Contracts\RuntimeSettingsResolverInterface;
use JOOservices\LaravelFlickr\Events\FlickrCallCompleted;
use JOOservices\LaravelFlickr\Events\FlickrCallFailed;
use JOOservices\LaravelFlickr\Events\FlickrClientResolved;
use JOOservices\LaravelFlickr\Events\FlickrContactRemoved;
use JOOservices\LaravelFlickr\Events\FlickrOAuthCompleted;
use JOOservices\LaravelFlickr\Events\FlickrOAuthRevoked;
use JOOservices\LaravelFlickr\Events\FlickrPhotoGroupRemoved;
use JOOservices\LaravelFlickr\Events\FlickrPhotoRemoved;
use JOOservices\LaravelFlickr\Events\FlickrPhotoUnfavorited;
use JOOservices\LaravelFlickr\Events\FlickrRateLimitApproaching;
use JOOservices\LaravelFlickr\Events\FlickrRateLimited;
use JOOservices\LaravelFlickr\Listeners\LogFlickrActivity;
use JOOservices\LaravelFlickr\Listeners\PersistFlickrData;
use JOOservices\LaravelFlickr\Listeners\RecordFlickrEvent;
use JOOservices\LaravelFlickr\OAuth\OAuthCompletionService;
use JOOservices\LaravelFlickr\OAuth\OAuthService;
use JOOservices\LaravelFlickr\OAuth\PendingAuthorizationStore;
use JOOservices\LaravelFlickr\RateLimit\RedisRequestLimiter;
use JOOservices\LaravelFlickr\RateLimit\RequestLimiterInterface;
use JOOservices\LaravelFlickr\Repositories\ActivityLogRepository;
use JOOservices\LaravelFlickr\Repositories\AppRepository;
use JOOservices\LaravelFlickr\Repositories\ContactRepository;
use JOOservices\LaravelFlickr\Repositories\EventRepository;
use JOOservices\LaravelFlickr\Repositories\PhotoFavoriteRepository;
use JOOservices\LaravelFlickr\Repositories\PhotoGroupRepository;
use JOOservices\LaravelFlickr\Repositories\PhotoRepository;
use JOOservices\LaravelFlickr\Repositories\TokenRepository;
use JOOservices\LaravelFlickr\Service\ActivityLogService;
use JOOservices\LaravelFlickr\Service\FlickrCallService;
use JOOservices\LaravelFlickr\Service\FlickrService;
use JOOservices\LaravelFlickr\Service\PersistenceReconcileService;
use JOOservices\LaravelFlickr\Service\StoredEventService;
use JOOservices\LaravelFlickr\Support\FlickrAdapterRegistry;
use JOOservices\LaravelFlickr\Support\LaravelConfigRateLimitResolver;
use JOOservices\LaravelFlickr\Support\LaravelConfigRuntimeSettingsResolver;

final class LaravelFlickrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-flickr.php', 'laravel-flickr');

        $this->registerResolvers();
        $this->registerCoreServices();
        $this->registerRepositories();
        $this->registerAdapters();
    }

    public function boot(): void
    {
        $this->registerEventListeners();
        $this->loadRoutesFrom(__DIR__.'/../routes/oauth.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laravel-flickr.php' => config_path('laravel-flickr.php'),
            ], 'laravel-flickr-config');

            $this->commands([
                FlickrAppAddCommand::class,
                FlickrOAuthAuthorizeCommand::class,
                FlickrOAuthCompleteCommand::class,
                FlickrOAuthRevokeCommand::class,
                FlickrInstallIndexesCommand::class,
                FlickrDoctorCommand::class,
                FlickrRateLimitStatusCommand::class,
            ]);
        }
    }

    private function registerResolvers(): void
    {
        $this->app->singleton(RateLimitConfigResolverInterface::class, LaravelConfigRateLimitResolver::class);
        $this->app->singleton(RuntimeSettingsResolverInterface::class, LaravelConfigRuntimeSettingsResolver::class);
    }

    private function registerCoreServices(): void
    {
        $this->app->singleton(FlickrClientFactory::class);
        $this->app->bind(FlickrClientFactoryInterface::class, FlickrClientFactory::class);
        $this->app->singleton(
            OAuthService::class,
            fn (): OAuthService => new OAuthService(
                null,
                $this->app->make(TokenRepository::class),
            ),
        );
        $this->app->singleton(PendingAuthorizationStore::class);
        $this->app->singleton(OAuthCompletionService::class);
        // Always bind RedisRequestLimiter; enabled() is checked on each acquire/status
        // so laravel-config toggles apply without recycling the worker.
        $this->app->singleton(RequestLimiterInterface::class, RedisRequestLimiter::class);
        $this->app->singleton(FlickrCallService::class);
        $this->app->singleton(FlickrService::class);
        $this->app->alias(FlickrService::class, 'flickr');
        $this->app->singleton(PersistenceReconcileService::class);
    }

    private function registerRepositories(): void
    {
        $this->app->singleton(AppRepository::class);
        $this->app->singleton(TokenRepository::class);
        $this->app->singleton(PhotoRepository::class);
        $this->app->singleton(ContactRepository::class);
        $this->app->singleton(PhotoGroupRepository::class);
        $this->app->singleton(PhotoFavoriteRepository::class);

        $this->app->bind(ActivityLogRepository::class);
        $this->app->bind(EventRepository::class);
        $this->app->bind(ActivityLogService::class);
        $this->app->bind(StoredEventService::class);
    }

    private function registerAdapters(): void
    {
        foreach (FlickrAdapterRegistry::classes() as $adapter) {
            $this->app->bind($adapter);
        }
    }

    private function registerEventListeners(): void
    {
        Event::listen(FlickrCallCompleted::class, [LogFlickrActivity::class, 'handleCallCompleted']);
        Event::listen(FlickrCallCompleted::class, [RecordFlickrEvent::class, 'handleCallCompleted']);
        Event::listen(FlickrCallCompleted::class, [PersistFlickrData::class, 'handle']);
        Event::listen(FlickrCallFailed::class, [LogFlickrActivity::class, 'handleCallFailed']);
        Event::listen(FlickrRateLimited::class, [LogFlickrActivity::class, 'handleRateLimited']);
        Event::listen(FlickrRateLimitApproaching::class, [LogFlickrActivity::class, 'handleRateLimitApproaching']);
        Event::listen(FlickrOAuthCompleted::class, [LogFlickrActivity::class, 'handleOAuthCompleted']);
        Event::listen(FlickrOAuthCompleted::class, [RecordFlickrEvent::class, 'handleOAuthCompleted']);
        Event::listen(FlickrOAuthRevoked::class, [LogFlickrActivity::class, 'handleOAuthRevoked']);
        Event::listen(FlickrOAuthRevoked::class, [RecordFlickrEvent::class, 'handleOAuthRevoked']);
        Event::listen(FlickrClientResolved::class, [LogFlickrActivity::class, 'handleClientResolved']);
        Event::listen(FlickrContactRemoved::class, [LogFlickrActivity::class, 'handleContactRemoved']);
        Event::listen(FlickrContactRemoved::class, [RecordFlickrEvent::class, 'handleContactRemoved']);
        Event::listen(FlickrPhotoRemoved::class, [LogFlickrActivity::class, 'handlePhotoRemoved']);
        Event::listen(FlickrPhotoRemoved::class, [RecordFlickrEvent::class, 'handlePhotoRemoved']);
        Event::listen(FlickrPhotoGroupRemoved::class, [LogFlickrActivity::class, 'handlePhotoGroupRemoved']);
        Event::listen(FlickrPhotoGroupRemoved::class, [RecordFlickrEvent::class, 'handlePhotoGroupRemoved']);
        Event::listen(FlickrPhotoUnfavorited::class, [LogFlickrActivity::class, 'handlePhotoUnfavorited']);
        Event::listen(FlickrPhotoUnfavorited::class, [RecordFlickrEvent::class, 'handlePhotoUnfavorited']);
    }
}
