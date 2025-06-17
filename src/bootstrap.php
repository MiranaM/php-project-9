<?php

use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;
use Valitron\Validator;
use Slim\Flash\Messages;
use Slim\Exception\HttpNotFoundException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

$container = new Container();

$container->set('renderer', function () {
    return new PhpRenderer(__DIR__ . '/../templates');
});

$container->set('flash', function () {
    return new Messages();
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
$app = AppFactory::create();

// Явно получаем контейнер, чтобы PHPStan не ругался
$appContainer = $app->getContainer();
if (!$appContainer instanceof \Psr\Container\ContainerInterface) {
    throw new \RuntimeException('DI контейнер не инициализирован');
}

Validator::lang('ru');

$errorMiddleware = $app->addErrorMiddleware(true, true, true);

$errorMiddleware->setDefaultErrorHandler(function (
    Request $request,
    Throwable $exception,
    bool $displayErrorDetails
) use ($appContainer) {
    $renderer = $appContainer->get('renderer');
    $response = new \Slim\Psr7\Response();

    $statusCode = $exception instanceof HttpNotFoundException ? 404 : 500;

    return $renderer->render($response->withStatus($statusCode), 'error.phtml', [
        'title' => 'Произошла ошибка',
        'message' => $exception->getMessage()
    ]);
});

(require_once __DIR__ . '/routes/home.php')($app);
(require_once __DIR__ . '/routes/urls.php')($app);

return $app;
