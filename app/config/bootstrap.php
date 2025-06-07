<?php

use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;
use Valitron\Validator;

$container = new Container();

$container->set('renderer', function () {
    return new PhpRenderer(__DIR__ . '/../../templates');
});

$container->set('pdo', function () {
    $databaseUrl = getenv('DATABASE_URL');

    if (!is_string($databaseUrl)) {
        throw new RuntimeException('DATABASE_URL is not set or invalid.');
    }

    $db = parse_url($databaseUrl);

    if ($db === false) {
        throw new RuntimeException('Failed to parse DATABASE_URL.');
    }
    $port = $db['port'] ?? 5432;
    $dsn = "pgsql:host={$db['host']};port={$port};dbname=" . ltrim($db['path'], '/');
    return new PDO($dsn, $db['user'], $db['pass']);
});

AppFactory::setContainer($container);
Validator::lang('ru');

return AppFactory::create();
