<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Repositories;

use InvalidArgumentException;
use JOOservices\LaravelFlickr\Dto\OAuthToken;
use JOOservices\LaravelFlickr\Models\Token;
use JOOservices\LaravelFlickr\Repositories\TokenRepository;
use JOOservices\LaravelFlickr\Tests\Support\FlickrNsid;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class TokenRepositoryTest extends TestCase
{
    private TokenRepository $tokens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->clearFlickrCollections();
        $this->tokens = app(TokenRepository::class);
        $this->storeApp();
    }

    #[Test]
    public function store_find_and_exists_round_trip_with_encryption(): void
    {
        $nsid = FlickrNsid::fake();
        $oauthToken = fake()->sha1();
        $secret = fake()->sha1();
        $username = fake()->userName();

        $this->tokens->store(
            $this->defaultAppName,
            new OAuthToken($oauthToken, $secret, $nsid, $username, fake()->name()),
        );

        $this->assertTrue($this->tokens->exists($this->defaultAppName, $nsid));

        $found = $this->tokens->find($this->defaultAppName, $nsid);
        $this->assertNotNull($found);
        $this->assertSame($oauthToken, $found->oauthToken);
        $this->assertSame($secret, $found->oauthTokenSecret);
        $this->assertSame($username, $found->username);

        $raw = Token::query()->where('app_name', $this->defaultAppName)->where('nsid', $nsid)->first();
        $this->assertNotNull($raw);
        $this->assertNotSame($oauthToken, $raw->getAttributes()['oauth_token'] ?? $oauthToken);
    }

    #[Test]
    public function find_returns_null_for_unknown_nsid(): void
    {
        $this->assertNull($this->tokens->find($this->defaultAppName, FlickrNsid::fake()));
        $this->assertFalse($this->tokens->exists($this->defaultAppName, FlickrNsid::fake()));
    }

    #[Test]
    public function store_rejects_token_without_nsid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->tokens->store($this->defaultAppName, new OAuthToken(fake()->sha1(), fake()->sha1()));
    }

    #[Test]
    public function store_rejects_blank_app_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->tokens->store('  ', new OAuthToken(fake()->sha1(), fake()->sha1(), FlickrNsid::fake()));
    }

    #[Test]
    public function forget_removes_the_token(): void
    {
        $token = $this->storeToken();
        $this->tokens->forget($this->defaultAppName, $token->userNsid);

        $this->assertFalse($this->tokens->exists($this->defaultAppName, $token->userNsid));
        $this->assertNull($this->tokens->find($this->defaultAppName, $token->userNsid));
    }

    #[Test]
    public function same_nsid_can_exist_under_two_apps(): void
    {
        $nsid = FlickrNsid::fake();
        $this->storeApp('backup', 'backup-key', 'backup-secret');

        $this->tokens->store(
            $this->defaultAppName,
            new OAuthToken(fake()->sha1(), fake()->sha1(), $nsid, 'a', null),
        );
        $this->tokens->store(
            'backup',
            new OAuthToken(fake()->sha1(), fake()->sha1(), $nsid, 'b', null),
        );

        $this->assertTrue($this->tokens->exists($this->defaultAppName, $nsid));
        $this->assertTrue($this->tokens->exists('backup', $nsid));
        $this->assertSame('a', $this->tokens->find($this->defaultAppName, $nsid)?->username);
        $this->assertSame('b', $this->tokens->find('backup', $nsid)?->username);
    }
}
