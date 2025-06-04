<?php

use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;
use Valitron\Validator;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

session_start();

$container = new Container();
$container->set('renderer', function () {
    return new PhpRenderer(__DIR__ . '/../templates');
});
AppFactory::setContainer($container);
$app = AppFactory::create();

$pdo = null;
if (isset($_ENV['DATABASE_URL'])) {
    $db = parse_url($_ENV['DATABASE_URL']);
    $dsn = "pgsql:host={$db['host']};port={$db['port']};dbname=" . ltrim($db['path'], '/');
    $pdo = new PDO($dsn, $db['user'], $db['pass']);
}

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
        return $response
            ->withHeader('Location', '/')
            ->withStatus(302);
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
    $stmt = $pdo->query('SELECT * FROM urls ORDER BY id DESC');
    $urls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $this->get('renderer')->render($response, 'urls/home.phtml', ['urls' => $urls]);
})->setName('urls');

$app->get('/urls/{id}', function ($request, $response, $args) use ($pdo) {
    $id = $args['id'];
    $stmt = $pdo->prepare('SELECT * FROM urls WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $url = $stmt->fetch(PDO::FETCH_ASSOC);
    return $this->get('renderer')->render($response, 'urls/show.phtml', ['url' => $url]);
})->setName('url.show');

$app->run();
