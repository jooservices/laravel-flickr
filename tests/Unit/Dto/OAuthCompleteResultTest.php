<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Dto;

use JOOservices\LaravelFlickr\Dto\OAuthCompleteResult;
use JOOservices\LaravelFlickr\Dto\OAuthToken;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class OAuthCompleteResultTest extends TestCase
{
    #[Test]
    public function success_and_failure_factories(): void
    {
        $token = new OAuthToken('a', 'b', '1@N00', 'u', 'F');
        $ok = OAuthCompleteResult::success($token, 'default', 'c1');
        $this->assertTrue($ok->ok);
        $this->assertSame('default', $ok->appName);
        $this->assertSame('c1', $ok->correlationId);

        $fail = OAuthCompleteResult::failure('nope', 404);
        $this->assertFalse($fail->ok);
        $this->assertSame(404, $fail->status);
        $this->assertSame('nope', $fail->error);
    }
}
