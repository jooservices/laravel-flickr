<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Tests\Unit\Dto;

use InvalidArgumentException;
use Jooservices\LaravelFlickr\Dto\OAuthToken;
use Jooservices\LaravelFlickr\Tests\TestCase;
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
}
