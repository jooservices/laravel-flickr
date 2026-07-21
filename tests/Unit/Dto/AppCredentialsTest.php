<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Tests\Unit\Dto;

use Jooservices\LaravelFlickr\Dto\AppCredentials;
use Jooservices\LaravelFlickr\Exceptions\MissingCredentialsException;
use Jooservices\LaravelFlickr\Tests\TestCase;
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
