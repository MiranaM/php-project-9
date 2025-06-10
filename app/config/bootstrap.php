<?php

use App\Exceptions\ConfigurationException;

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

    $host = $db['host'] ?? throw new RuntimeException('DATABASE_URL missing host');
    $path = $db['path'] ?? throw new RuntimeException('DATABASE_URL missing path');
    $user = $db['user'] ?? throw new RuntimeException('DATABASE_URL missing user');
    $pass = $db['pass'] ?? throw new RuntimeException('DATABASE_URL missing pass');
    $port = $db['port'] ?? 5432;

    $dsn = "pgsql:host={$host};port={$port};dbname=" . ltrim($path, '/');

    return new PDO($dsn, $user, $pass);
});

AppFactory::setContainer($container);
Validator::lang('ru');

$app = AppFactory::create();

(require_once __DIR__ . '/../routes/home.php')($app);
(require_once __DIR__ . '/../routes/urls.php')($app);

return $app;
