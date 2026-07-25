<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Http\Controllers;

use Illuminate\Http\JsonResponse;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use JOOservices\LaravelFlickr\Http\Requests\OAuthCallbackRequest;
use JOOservices\LaravelFlickr\Http\Resources\OAuthCallbackResource;
use JOOservices\LaravelFlickr\OAuth\OAuthCompletionService;

final class OAuthCallbackController extends BaseApiController
{
    public function __invoke(
        OAuthCallbackRequest $request,
        OAuthCompletionService $completion,
    ): JsonResponse {
        $result = $completion->completePending(
            $request->oauthToken(),
            $request->oauthVerifier(),
        );

        if (! $result->ok || $result->token === null) {
            return $this->respondWithError(
                message: $result->error ?? 'Authorization failed.',
                code: $result->status,
            );
        }

        return $this->respondWithData(
            (new OAuthCallbackResource(
                $result->token->userNsid,
                $result->token->username,
                $result->correlationId,
            ))->resolve(),
            message: 'Flickr authorization completed.',
        );
    }
}
