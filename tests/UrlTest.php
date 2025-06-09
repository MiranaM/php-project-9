<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class UrlTest extends TestCase
{
    public function testValidUrl(): void
    {
        $url = 'https://example.com';
        $result = filter_var($url, FILTER_VALIDATE_URL);
        self::assertNotFalse($result);
    }

    public function testInvalidUrl(): void
    {
        $url = 'not-a-url';
        $result = filter_var($url, FILTER_VALIDATE_URL);
        self::assertFalse($result);
    }
}
