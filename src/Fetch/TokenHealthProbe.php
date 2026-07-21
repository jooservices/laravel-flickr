<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Fetch;

use JOOservices\Flickr\DTO\Common\RequestOptionsData;
use JOOservices\Flickr\Flickr;
use Jooservices\LaravelFlickr\Dto\TokenHealthResult;
use Throwable;

/**
 * Single flickr.test.login probe — sync only.
 */
final class TokenHealthProbe
{
    public function probe(Flickr $client): TokenHealthResult
    {
        try {
            $response = $client->raw()->call(
                'flickr.test.login',
                [],
                new RequestOptionsData(authenticated: true),
            );

            if (! $response->ok) {
                return new TokenHealthResult(
                    valid: false,
                    errorCode: $response->error?->code,
                    errorMessage: $response->error?->message,
                );
            }

            $user = $response->data['user'] ?? null;
            $nsid = null;
            if (is_array($user)) {
                $id = $user['id'] ?? $user['nsid'] ?? null;
                $nsid = is_string($id) ? $id : null;
            }

            return new TokenHealthResult(valid: true, userNsid: $nsid);
        } catch (Throwable $exception) {
            return new TokenHealthResult(
                valid: false,
                errorMessage: $exception->getMessage(),
            );
        }
    }
}
