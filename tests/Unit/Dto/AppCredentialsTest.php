<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Dto;

use JOOservices\LaravelFlickr\Dto\AppCredentials;
use JOOservices\LaravelFlickr\Exceptions\MissingCredentialsException;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class AppCredentialsTest extends TestCase
{
    #[Test]
    public function it_rejects_empty_credentials(): void
    {
        $this->expectException(MissingCredentialsException::class);
        new AppCredentials('', 'secret');
    }
}
