<?php

use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;
use Valitron\Validator;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;

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
$app = AppFactory::create();

Validator::lang('ru');

(require __DIR__ . '/../routes/home.php')($app);
(require __DIR__ . '/../routes/urls.php')($app);

$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler(function (
    ServerRequestInterface $request,
    Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails
) use ($app) {
    $response = new Response();
    $statusCode = $exception instanceof HttpNotFoundException ? 404 : 500;
    $message = $statusCode === 404
        ? 'Страница не найдена'
        : 'Упс, что-то пошло не так';

    $renderer = $app->getContainer()->get('renderer');
    return $renderer->render($response->withStatus($statusCode), 'error.phtml', [
        'message' => $message,
        'status' => $statusCode
    ]);
});

return $app;
