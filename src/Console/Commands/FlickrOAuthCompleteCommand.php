<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Console\Commands;

use Illuminate\Console\Command;
use JOOservices\LaravelFlickr\Exceptions\AppNotFoundException;
use JOOservices\LaravelFlickr\OAuth\OAuthService;
use JOOservices\LaravelFlickr\OAuth\PendingAuthorizationStore;
use JOOservices\LaravelFlickr\Repositories\AppRepository;

final class FlickrOAuthCompleteCommand extends Command
{
    protected $signature = 'flickr:oauth:complete
                            {--oauth-token= : Request token from authorize step}
                            {--verifier= : OAuth verifier from Flickr}';

    protected $description = 'Complete Flickr OAuth after authorization';

    public function handle(
        OAuthService $oauth,
        AppRepository $apps,
        PendingAuthorizationStore $pending,
    ): int {
        $oauthToken = $this->option('oauth-token');
        $verifier = $this->option('verifier');

        if (! is_string($oauthToken) || $oauthToken === '' || ! is_string($verifier) || $verifier === '') {
            $this->error('Both --oauth-token and --verifier are required.');

            return self::FAILURE;
        }

        $pendingAuth = $pending->consume($oauthToken);
        if ($pendingAuth === null) {
            $this->error('Authorization request not found or expired.');

            return self::FAILURE;
        }

        $app = $apps->find($pendingAuth->appName);
        if ($app === null) {
            throw new AppNotFoundException($pendingAuth->appName);
        }

        $token = $oauth->complete(
            $app->credentials(),
            $pendingAuth->appName,
            $oauthToken,
            $verifier,
            $pendingAuth->oauthTokenSecret,
            $pendingAuth->correlationId,
        );

        $this->info("Connected Flickr account: {$token->username} ({$token->userNsid}) on [{$pendingAuth->appName}]");
        $this->comment(
            "Use FlickrService::connection('{$pendingAuth->appName}')->as('{$token->userNsid}') for future calls.",
        );

        return self::SUCCESS;
    }
}
