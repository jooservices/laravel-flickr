<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Dto;

use InvalidArgumentException;
use JOOservices\Flickr\DTO\Auth\AccessTokenData;

/**
 * User OAuth 1.0a access token for account-bound Flickr calls.
 */
final readonly class OAuthToken
{
    public function __construct(
        public string $oauthToken,
        public string $oauthTokenSecret,
        public ?string $userNsid = null,
        public ?string $username = null,
        public ?string $fullname = null,
    ) {
        if (trim($this->oauthToken) === '' || trim($this->oauthTokenSecret) === '') {
            throw new InvalidArgumentException('Flickr OAuth token and secret are required.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload  Accepts camelCase or snake_case OAuth keys
     */
    public static function fromArray(array $payload): self
    {
        $token = $payload['oauthToken'] ?? $payload['oauth_token'] ?? '';
        $secret = $payload['oauthTokenSecret'] ?? $payload['oauth_token_secret'] ?? '';

        return new self(
            oauthToken: is_string($token) ? $token : '',
            oauthTokenSecret: is_string($secret) ? $secret : '',
            userNsid: self::nullableString($payload['userNsid'] ?? $payload['user_nsid'] ?? null),
            username: self::nullableString($payload['username'] ?? null),
            fullname: self::nullableString($payload['fullname'] ?? null),
        );
    }

    public static function fromJson(string $json): self
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('OAuth token JSON must decode to an object.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $decoded;

        return self::fromArray($payload);
    }

    public function toAccessTokenData(): AccessTokenData
    {
        return AccessTokenData::from([
            'oauthToken' => $this->oauthToken,
            'oauthTokenSecret' => $this->oauthTokenSecret,
            'userNsid' => $this->userNsid,
            'username' => $this->username,
            'fullname' => $this->fullname,
        ]);
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
