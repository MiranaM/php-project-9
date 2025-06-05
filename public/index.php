<?php

use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Valitron\Validator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Validators\UrlValidator;

require __DIR__ . '/../vendor/autoload.php';

session_start();

Validator::addRule('validUrl', function ($field, $value, array $params, array $fields) {
    $parsed = parse_url($value);
    if (!isset($parsed['scheme'], $parsed['host'])) {
        return false;
    }
    return filter_var($parsed['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
}, '{field} указан некорректно');

$container = new Container();
$container->set('renderer', function () {
    return new PhpRenderer(__DIR__ . '/../templates');
});
AppFactory::setContainer($container);
$app = AppFactory::create();

$databaseUrl = getenv('DATABASE_URL');
$db = parse_url($databaseUrl);
$port = $db['port'] ?? 5432;
$dsn = "pgsql:host={$db['host']};port={$port};dbname=" . ltrim($db['path'], '/');
$pdo = new PDO($dsn, $db['user'], $db['pass']);

$app->get('/', function ($request, $response) {
    $errors = $_SESSION['errors'] ?? [];
    $old = $_SESSION['old'] ?? [];
    $flash = $_SESSION['flash'] ?? null;

    unset($_SESSION['errors'], $_SESSION['old'], $_SESSION['flash']);

    return $this->get('renderer')->render($response, 'home.phtml', [
        'errors' => $errors,
        'old' => $old,
        'flash' => $flash
    ]);
});

$app->post('/urls', function (Request $request, Response $response) use ($pdo) {
    $data = $request->getParsedBody()['url'] ?? [];
    $url = trim($data['name'] ?? '');

    $errors = UrlValidator::validate(['url' => ['name' => $url]]);
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        $_SESSION['old'] = $data;
        return $response->withHeader('Location', '/')->withStatus(302);
    }

    $parsed = parse_url($url);
    $normalizedUrl = "{$parsed['scheme']}://{$parsed['host']}";

    $stmt = $pdo->prepare('
        SELECT id
        FROM urls
        WHERE name = :name
    ');
    $stmt->execute(['name' => $normalizedUrl]);
    $existing = $stmt->fetch();

    if (!$existing) {
        $stmt = $pdo->prepare('
            INSERT INTO urls (name, created_at)
            VALUES (:name, NOW())
        ');
        $stmt->execute(['name' => $normalizedUrl]);
        $_SESSION['flash'] = 'URL успешно добавлен';
    } else {
        $_SESSION['flash'] = 'URL уже существует';
    }

    return $response
        ->withHeader('Location', '/urls')
        ->withStatus(302);
});


$app->get('/urls', function ($request, $response) use ($pdo) {
    $stmt = $pdo->query('
        SELECT urls.*,
               MAX(url_checks.created_at) AS last_check,
               MAX(url_checks.status_code) AS last_status
        FROM urls
        LEFT JOIN url_checks ON urls.id = url_checks.url_id
        GROUP BY urls.id
        ORDER BY urls.id DESC
    ');
    $urls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $this
        ->get('renderer')
        ->render($response, 'urls/index.phtml', ['urls' => $urls]);
})->setName('urls');

$app->get('/urls/{id}', function ($request, $response, $args) use ($pdo) {
    $id = $args['id'];
    $stmt = $pdo->prepare('
        SELECT *
        FROM urls
        WHERE id = :id
    ');
    $stmt->execute(['id' => $id]);
    $url = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('
        SELECT *
        FROM url_checks
        WHERE url_id = :url_id
        ORDER BY created_at DESC
    ');
    $stmt->execute(['url_id' => $id]);
    $checks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $this
        ->get('renderer')
        ->render($response, 'urls/show.phtml', ['url' => $url, 'checks' => $checks]);
})->setName('url.show');

$app->post('/urls/{id}/checks', function ($request, $response, $args) use ($pdo) {
    $urlId = $args['id'];

    $stmt = $pdo->prepare('
        SELECT name
        FROM urls
        WHERE id = :id
    ');
    $stmt->execute(['id' => $urlId]);
    $url = $stmt->fetchColumn();

    if (!$url) {
        $_SESSION['flash'] = 'Сайт не найден';
        return $response->withHeader('Location', "/urls/{$urlId}")->withStatus(302);
    }

    $client = new Client(['timeout' => 10]);

    try {
        $res = $client->request('GET', $url);
        $statusCode = $res->getStatusCode();

        $stmt = $pdo->prepare('
            INSERT INTO url_checks (url_id, status_code, created_at)
            VALUES (:url_id, :status_code, NOW())
        ');
        $stmt->execute([
            'url_id' => $urlId,
            'status_code' => $statusCode
        ]);

        $_SESSION['flash'] = "Проверка выполнена. Код ответа: {$statusCode}";
    } catch (ConnectException $e) {
        $_SESSION['flash'] = 'Сервер не найден. Проверка не выполнена.';
    } catch (RequestException $e) {
        $_SESSION['flash'] = 'Ошибка HTTP. Проверка не выполнена.';
    } catch (\Exception $e) {
        $_SESSION['flash'] = 'Неизвестная ошибка. Проверка не выполнена.';
    }

    return $response
        ->withHeader('Location', "/urls/{$urlId}")
        ->withStatus(302);
});

$app->run();
