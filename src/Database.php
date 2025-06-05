<?php

namespace App;

use PDO;

class Database
{
    public static function getPDO(): PDO
    {
        $url = parse_url($_ENV['DATABASE_URL']);

        $host = $url['host'];
        $port = $url['port'];
        $db = ltrim($url['path'], '/');
        $user = $url['user'];
        $pass = $url['pass'] ?? '';

        $dsn = "pgsql:host=$host;port=$port;dbname=$db";

        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }
}
