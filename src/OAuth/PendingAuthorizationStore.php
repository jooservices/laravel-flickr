<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\OAuth;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redis;
use JOOservices\LaravelFlickr\Contracts\RuntimeSettingsResolverInterface;
use Throwable;

final class PendingAuthorizationStore
{
    public function __construct(
        private readonly RuntimeSettingsResolverInterface $runtimeSettings,
    ) {}

    public function put(
        string $oauthToken,
        string $oauthTokenSecret,
        string $appName,
        ?string $correlationId,
        int $ttlSeconds,
    ): void {
        $key = $this->key($oauthToken);
        $payload = json_encode([
            'secret' => $oauthTokenSecret,
            'app_name' => $appName,
            'correlation_id' => $correlationId,
        ], JSON_THROW_ON_ERROR);

        Redis::setex($key, max(1, $ttlSeconds), Crypt::encryptString($payload));
    }

    public function consume(string $oauthToken): ?PendingAuthorization
    {
        $key = $this->key($oauthToken);
        $result = Redis::connection()->command('eval', [$this->consumeScript(), [$key], 1]);

        if (! is_string($result) || $result === '') {
            return null;
        }

        try {
            $plaintext = Crypt::decryptString($result);
            /** @var array{secret?: string, app_name?: string, correlation_id?: string|null} $decoded */
            $decoded = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        $secret = $decoded['secret'] ?? null;
        $appName = $decoded['app_name'] ?? null;

        if (! is_string($secret) || $secret === '' || ! is_string($appName) || $appName === '') {
            return null;
        }

        $correlationId = $decoded['correlation_id'] ?? null;

        return new PendingAuthorization(
            $secret,
            $appName,
            is_string($correlationId) ? $correlationId : null,
        );
    }

    private function key(string $oauthToken): string
    {
        return $this->runtimeSettings->oauthPendingKeyPrefix().':pending:'.$oauthToken;
    }

    private function consumeScript(): string
    {
        return <<<'LUA'
local value = redis.call('GET', KEYS[1])
if not value then
    return ''
end
redis.call('DEL', KEYS[1])
return value
LUA;
    }
}
