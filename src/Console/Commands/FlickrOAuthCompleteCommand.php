<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Console\Commands;

use Illuminate\Console\Command;
use JOOservices\LaravelFlickr\OAuth\OAuthCompletionService;

final class FlickrOAuthCompleteCommand extends Command
{
    protected $signature = 'flickr:oauth:complete
                            {--oauth-token= : Request token from authorize step}
                            {--verifier= : OAuth verifier from Flickr}';

    protected $description = 'Complete Flickr OAuth after authorization';

    public function handle(OAuthCompletionService $completion): int
    {
        $oauthToken = $this->option('oauth-token');
        $verifier = $this->option('verifier');

        if (! is_string($oauthToken) || $oauthToken === '' || ! is_string($verifier) || $verifier === '') {
            $this->error('Both --oauth-token and --verifier are required.');

            return self::FAILURE;
        }

        $result = $completion->completePending($oauthToken, $verifier);
        if (! $result->ok || $result->token === null || $result->appName === null) {
            $this->error($result->error ?? 'Authorization failed.');

            return self::FAILURE;
        }

        $token = $result->token;
        $this->info("Connected Flickr account: {$token->username} ({$token->userNsid}) on [{$result->appName}]");
        $this->comment(
            "Use FlickrService::connection('{$result->appName}')->as('{$token->userNsid}') for future calls.",
        );

        return self::SUCCESS;
    }
}
