<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Console\Commands;

use Illuminate\Console\Command;
use JOOservices\LaravelFlickr\Contracts\RuntimeSettingsResolverInterface;
use JOOservices\LaravelFlickr\Exceptions\AppNotFoundException;
use JOOservices\LaravelFlickr\OAuth\OAuthService;
use JOOservices\LaravelFlickr\OAuth\PendingAuthorizationStore;
use JOOservices\LaravelFlickr\Repositories\AppRepository;

final class FlickrOAuthAuthorizeCommand extends Command
{
    protected $signature = 'flickr:oauth:authorize
                            {connection? : Flickr API app connection name}
                            {--callback-url= : Web redirect callback URL; omit for OOB mode}
                            {--correlation-id= : Opaque host identifier echoed on completion}';

    protected $description = 'Start Flickr OAuth authorization (OOB or web redirect)';

    public function handle(
        OAuthService $oauth,
        AppRepository $apps,
        PendingAuthorizationStore $pending,
        RuntimeSettingsResolverInterface $runtime,
    ): int {
        $connection = $this->argument('connection');
        $appName = is_string($connection) && $connection !== ''
            ? $connection
            : $runtime->defaultConnection();

        $app = $apps->find($appName);
        if ($app === null) {
            throw new AppNotFoundException($appName);
        }

        $callbackUrl = $this->option('callback-url');
        $callbackUrl = is_string($callbackUrl) && $callbackUrl !== '' ? $callbackUrl : null;
        $correlationId = $this->option('correlation-id');
        $correlationId = is_string($correlationId) && $correlationId !== '' ? $correlationId : null;

        $begin = $oauth->authorize($app->credentials(), callbackUrl: $callbackUrl);

        $pending->put(
            $begin->requestToken,
            $begin->requestTokenSecret,
            $appName,
            $correlationId,
            ttlSeconds: $runtime->oauthPendingTtlSeconds(),
        );

        $this->info("Authorization URL: {$begin->authorizationUrl}");
        $this->line("oauth_token: {$begin->requestToken}");
        $this->line("connection: {$appName}");

        if ($callbackUrl === null) {
            $this->comment('Visit the URL above, authorize, then run flickr:oauth:complete with the verifier Flickr gives you.');
        } else {
            $this->comment('Visit the URL above in a browser. Flickr will redirect back to your configured callback API once authorized.');
        }

        return self::SUCCESS;
    }
}
