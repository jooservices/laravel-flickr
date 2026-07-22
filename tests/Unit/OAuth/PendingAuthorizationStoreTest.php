<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\OAuth;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redis;
use JOOservices\LaravelConfig\Facades\Config;
use JOOservices\LaravelFlickr\OAuth\PendingAuthorizationStore;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class PendingAuthorizationStoreTest extends TestCase
{
    private PendingAuthorizationStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresRedis();
        $this->store = app(PendingAuthorizationStore::class);
    }

    #[Test]
    public function put_and_consume_round_trip(): void
    {
        $oauthToken = fake()->sha1();
        $secret = fake()->sha1();
        $correlationId = fake()->uuid();

        $this->store->put($oauthToken, $secret, $this->defaultAppName, $correlationId, 900);

        $pending = $this->store->consume($oauthToken);

        $this->assertNotNull($pending);
        $this->assertSame($secret, $pending->oauthTokenSecret);
        $this->assertSame($this->defaultAppName, $pending->appName);
        $this->assertSame($correlationId, $pending->correlationId);
        $this->assertNull($this->store->consume($oauthToken));
    }

    #[Test]
    public function consume_returns_null_when_missing(): void
    {
        $this->assertNull($this->store->consume(fake()->sha1()));
    }

    #[Test]
    public function consume_returns_null_for_corrupt_encrypted_payload(): void
    {
        $token = fake()->sha1();
        $key = Config::get('flickr.oauth_pending_key_prefix', 'laravel-flickr-oauth-test').':pending:'.$token;
        Redis::setex($key, 60, 'not-encrypted');

        $this->assertNull($this->store->consume($token));
    }

    #[Test]
    public function consume_returns_null_when_decrypted_secret_is_empty(): void
    {
        $token = fake()->sha1();
        $prefix = (string) Config::get('flickr.oauth_pending_key_prefix');
        $key = $prefix.':pending:'.$token;
        Redis::setex($key, 60, Crypt::encryptString(json_encode([
            'secret' => '',
            'app_name' => 'default',
        ], JSON_THROW_ON_ERROR)));

        $this->assertNull($this->store->consume($token));
    }
}
