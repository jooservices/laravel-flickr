<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\OAuth;

use JOOservices\LaravelFlickr\Console\Commands\FlickrOAuthCompleteCommand;
use JOOservices\LaravelFlickr\Dto\OAuthCompleteResult;
use JOOservices\LaravelFlickr\Repositories\AppRepository;

/**
 * Single orchestration path for finishing OAuth from pending Redis state.
 * Used by the HTTP callback and {@see FlickrOAuthCompleteCommand}.
 */
final class OAuthCompletionService
{
    public function __construct(
        private readonly OAuthService $oauth,
        private readonly PendingAuthorizationStore $pending,
        private readonly AppRepository $apps,
    ) {}

    public function completePending(string $oauthToken, string $oauthVerifier): OAuthCompleteResult
    {
        $pendingAuth = $this->pending->consume($oauthToken);
        if ($pendingAuth === null) {
            return OAuthCompleteResult::failure(
                'Authorization request not found or expired.',
                404,
            );
        }

        $app = $this->apps->find($pendingAuth->appName);
        if ($app === null) {
            return OAuthCompleteResult::failure(
                "No Flickr API app registered as [{$pendingAuth->appName}]. Run flickr:app:add {$pendingAuth->appName} first.",
                404,
            );
        }

        $token = $this->oauth->complete(
            $app->credentials(),
            $pendingAuth->appName,
            $oauthToken,
            $oauthVerifier,
            $pendingAuth->oauthTokenSecret,
            $pendingAuth->correlationId,
        );

        return OAuthCompleteResult::success($token, $pendingAuth->appName, $pendingAuth->correlationId);
    }
}
