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
    $db = parse_url($databaseUrl);
    $port = $db['port'] ?? 5432;
    $dsn = "pgsql:host={$db['host']};port={$port};dbname=" . ltrim($db['path'], '/');
    return new PDO($dsn, $db['user'], $db['pass']);
});

AppFactory::setContainer($container);
Validator::lang('ru');

return AppFactory::create();
