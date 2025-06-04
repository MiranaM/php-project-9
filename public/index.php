<?php

use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;
use Valitron\Validator;

require __DIR__ . '/../vendor/autoload.php';

session_start();

$container = new Container();
$container->set('renderer', function () {
    return new PhpRenderer(__DIR__ . '/../templates');
});
AppFactory::setContainer($container);
$app = AppFactory::create();

$databaseUrl = getenv('DATABASE_URL') ?: 'postgresql://postgres:postgres@localhost:5432/project9';
$db = parse_url($databaseUrl);
$port = $db['port'] ?? 5432;
$dsn = "pgsql:host={$db['host']};port={$port};dbname=" . ltrim($db['path'], '/');
$pdo = new PDO($dsn, $db['user'], $db['pass']);

$app->get('/', function ($request, $response) {
    return $this->get('renderer')->render($response, 'home.phtml');
})->setName('home');

$app->post('/urls', function ($request, $response) use ($pdo) {
    $data = $request->getParsedBody()['url'] ?? [];
    $url = trim($data['name'] ?? '');

    $v = new Validator(['name' => $url]);
    $v->rule('required', 'name');
    $v->rule('lengthMax', 'name', 255);
    $v->rule('url', 'name');

    if (!$v->validate()) {
        $_SESSION['errors'] = $v->errors();
        $_SESSION['old'] = $data;
        return $response->withHeader('Location', '/')->withStatus(302);
    }

    $stmt = $pdo->prepare('SELECT id FROM urls WHERE name = :name');
    $stmt->execute(['name' => $url]);
    $existing = $stmt->fetch();

    if (!$existing) {
        $stmt = $pdo->prepare('INSERT INTO urls (name, created_at) VALUES (:name, NOW())');
        $stmt->execute(['name' => $url]);
        $_SESSION['flash'] = 'URL успешно добавлен';
    } else {
        $_SESSION['flash'] = 'URL уже существует';
    }

    return $response->withHeader('Location', '/urls')->withStatus(302);
});

$app->get('/urls', function ($request, $response) use ($pdo) {
    $stmt = $pdo->query('
        SELECT urls.*, MAX(url_checks.created_at) AS last_check
        FROM urls
        LEFT JOIN url_checks ON urls.id = url_checks.url_id
        GROUP BY urls.id
        ORDER BY urls.id DESC
    ');
    $urls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $this->get('renderer')->render($response, 'urls/home.phtml', ['urls' => $urls]);
})->setName('urls');

$app->get('/urls/{id}', function ($request, $response, $args) use ($pdo) {
    $id = $args['id'];
    $stmt = $pdo->prepare('SELECT * FROM urls WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $url = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('SELECT * FROM url_checks WHERE url_id = :url_id ORDER BY created_at DESC');
    $stmt->execute(['url_id' => $id]);
    $checks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $this->get('renderer')->render($response, 'urls/show.phtml', ['url' => $url, 'checks' => $checks]);
})->setName('url.show');

$app->post('/urls/{id}/checks', function ($request, $response, $args) use ($pdo) {
    $urlId = $args['id'];
    $stmt = $pdo->prepare('INSERT INTO url_checks (url_id, created_at) VALUES (:url_id, NOW())');
    $stmt->execute(['url_id' => $urlId]);

    $_SESSION['flash'] = 'Проверка добавлена';
    return $response->withHeader('Location', "/urls/{$urlId}")->withStatus(302);
});

$app->run();
