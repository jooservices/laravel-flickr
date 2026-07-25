<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Repositories;

use InvalidArgumentException;
use JOOservices\LaravelFlickr\Dto\OAuthToken;
use JOOservices\LaravelFlickr\Models\Token;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;

/**
 * Token storage — keyed by (app_name, Flickr NSID), not Eloquent primary key.
 */
final class TokenRepository extends EloquentRepository
{
    public function __construct(Token $model)
    {
        parent::__construct($model);
    }

    public function exists(string $appName, string $nsid): bool
    {
        // Select only _id so encrypted token columns are not hydrated/decrypted.
        return $this->model->newQuery()
            ->where('app_name', $appName)
            ->where('nsid', $nsid)
            ->first(['_id']) !== null;
    }

    public function find(string $appName, string $nsid): ?OAuthToken
    {
        $row = $this->model->newQuery()
            ->where('app_name', $appName)
            ->where('nsid', $nsid)
            ->first();

        if (! $row instanceof Token) {
            return null;
        }

        return new OAuthToken(
            $row->oauth_token,
            $row->oauth_token_secret,
            $row->nsid,
            $row->username,
            $row->fullname,
        );
    }

    public function store(string $appName, OAuthToken $token): void
    {
        if ($token->userNsid === null || $token->userNsid === '') {
            throw new InvalidArgumentException('OAuthToken must include userNsid to be stored.');
        }

        if (trim($appName) === '') {
            throw new InvalidArgumentException('Flickr app name is required to store a token.');
        }

        $this->model->newQuery()->updateOrCreate(
            [
                'app_name' => $appName,
                'nsid' => $token->userNsid,
            ],
            [
                'oauth_token' => $token->oauthToken,
                'oauth_token_secret' => $token->oauthTokenSecret,
                'username' => $token->username,
                'fullname' => $token->fullname,
            ],
        );
    }

    public function forget(string $appName, string $nsid): void
    {
        $this->model->newQuery()
            ->where('app_name', $appName)
            ->where('nsid', $nsid)
            ->delete();
    }

    public function forgetByApp(string $appName): void
    {
        $this->model->newQuery()
            ->where('app_name', $appName)
            ->delete();
    }
}
