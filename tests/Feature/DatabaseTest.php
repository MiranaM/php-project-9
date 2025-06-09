<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

final class DatabaseTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $url = getenv('DATABASE_URL') ?: '';
        $db = parse_url($url);

        if (!is_array($db)) {
            throw new \RuntimeException('Invalid DATABASE_URL');
        }

        $host = $db['host'] ?? throw new \RuntimeException('Missing host');
        $path = $db['path'] ?? throw new \RuntimeException('Missing path');
        $user = $db['user'] ?? throw new \RuntimeException('Missing user');
        $pass = $db['pass'] ?? throw new \RuntimeException('Missing password');
        $port = $db['port'] ?? 5432;
        $dbName = ltrim($path, '/');

        $dsn = "pgsql:host=$host;port=$port;dbname=$dbName";
        $this->pdo = new PDO($dsn, $user, $pass);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function testInsertAndFetch(): void
    {
        $this->pdo->exec("TRUNCATE urls RESTART IDENTITY CASCADE");
        $this->pdo->exec("INSERT INTO urls (name, created_at) VALUES ('https://example.com', NOW())");

        /** @var PDOStatement|false $stmt */
        $stmt = $this->pdo->query("SELECT name FROM urls WHERE name = 'https://example.com'");

        if ($stmt === false) {
            self::fail('Query failed');
        }

        /** @var array{name: string}|false $result */
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($result)) {
            self::fail('Fetch returned false');
        }

        self::assertEquals('https://example.com', $result['name']);
    }
}
