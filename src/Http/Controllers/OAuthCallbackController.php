<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Http\Controllers;

use Illuminate\Http\JsonResponse;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use JOOservices\LaravelFlickr\Exceptions\AppNotFoundException;
use JOOservices\LaravelFlickr\Http\Requests\OAuthCallbackRequest;
use JOOservices\LaravelFlickr\Http\Resources\OAuthCallbackResource;
use JOOservices\LaravelFlickr\OAuth\OAuthService;
use JOOservices\LaravelFlickr\OAuth\PendingAuthorizationStore;
use JOOservices\LaravelFlickr\Repositories\AppRepository;

final class OAuthCallbackController extends BaseApiController
{
    public function __invoke(
        OAuthCallbackRequest $request,
        OAuthService $oauth,
        PendingAuthorizationStore $pending,
        AppRepository $apps,
    ): JsonResponse {
        $oauthToken = $request->oauthToken();
        $pendingAuth = $pending->consume($oauthToken);

        if ($pendingAuth === null) {
            return $this->respondWithError(message: 'Authorization request not found or expired.', code: 404);
        }

        $app = $apps->find($pendingAuth->appName);
        if ($app === null) {
            throw new AppNotFoundException($pendingAuth->appName);
        }

        $token = $oauth->complete(
            $app->credentials(),
            $pendingAuth->appName,
            $oauthToken,
            $request->oauthVerifier(),
            $pendingAuth->oauthTokenSecret,
            $pendingAuth->correlationId,
        );

        return $this->respondWithData(
            (new OAuthCallbackResource($token->userNsid, $token->username, $pendingAuth->correlationId))->resolve(),
            message: 'Flickr authorization completed.',
        );
    }
}
