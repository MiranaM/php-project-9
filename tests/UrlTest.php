<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class UrlTest extends TestCase
{
    public function testValidUrl(): void
    {
        $url = 'https://example.com';
        $this->assertTrue(filter_var($url, FILTER_VALIDATE_URL) !== false);
    }

    public function testInvalidUrl(): void
    {
        $url = 'not-a-url';
        $this->assertFalse(filter_var($url, FILTER_VALIDATE_URL));
    }
}
