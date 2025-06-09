<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use PDO;

final class DatabaseTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $url = parse_url(getenv('DATABASE_URL'));

        $host = $url['host'];
        $port = $url['port'];
        $db   = ltrim($url['path'], '/');
        $user = $url['user'];
        $pass = $url['pass'];

        $dsn = "pgsql:host=$host;port=$port;dbname=$db";
        $this->pdo = new PDO($dsn, $user, $pass);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function testInsertAndFetch(): void
    {
        $this->pdo->exec("TRUNCATE urls RESTART IDENTITY CASCADE");
        $this->pdo->exec("INSERT INTO urls (name, created_at) VALUES ('https://example.com', NOW())");

        $stmt = $this->pdo->query("SELECT name FROM urls WHERE name = 'https://example.com'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('https://example.com', $result['name']);
    }
}
