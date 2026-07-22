<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Dto;

use InvalidArgumentException;
use JOOservices\LaravelFlickr\Dto\OAuthToken;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class OAuthTokenTest extends TestCase
{
    #[Test]
    public function it_builds_from_array_and_json(): void
    {
        $token = OAuthToken::fromArray([
            'oauth_token' => 'tok',
            'oauth_token_secret' => 'sec',
            'user_nsid' => '123@N01',
            'username' => 'alice',
        ]);

        $this->assertSame('tok', $token->oauthToken);
        $this->assertSame('sec', $token->oauthTokenSecret);
        $this->assertSame('123@N01', $token->userNsid);

        $fromJson = OAuthToken::fromJson(json_encode([
            'oauthToken' => 'tok2',
            'oauthTokenSecret' => 'sec2',
        ], JSON_THROW_ON_ERROR));

        $this->assertSame('tok2', $fromJson->oauthToken);
        $access = $fromJson->toAccessTokenData();
        $this->assertSame('tok2', $access->oauthToken);
    }

    #[Test]
    public function it_rejects_empty_token(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new OAuthToken('', 'secret');
    }

    #[Test]
    public function it_rejects_invalid_json(): void
    {
        $this->expectException(InvalidArgumentException::class);
        OAuthToken::fromJson('not-json');
    }

    #[Test]
    public function from_array_ignores_non_string_and_empty_optional_fields(): void
    {
        $token = OAuthToken::fromArray([
            'oauth_token' => 't',
            'oauth_token_secret' => 's',
            'user_nsid' => 123,
            'username' => '',
            'fullname' => null,
        ]);

        $this->assertNull($token->userNsid);
        $this->assertNull($token->username);
    }
}
