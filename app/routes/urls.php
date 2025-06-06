<?php

use Slim\Psr7\Response;
use Slim\Psr7\Request;
use DiDom\Document;
use GuzzleHttp\Client;

$app->get('/urls/{id}', function (Request $request, Response $response, $args) {
    $pdo = $this->get('pdo');
    $id = $args['id'];

    $stmt = $pdo->prepare('SELECT * FROM urls WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $url = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('SELECT * FROM url_checks WHERE url_id = :url_id ORDER BY created_at DESC');
    $stmt->execute(['url_id' => $id]);
    $checks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $this->get('renderer')->render($response, 'urls/show.phtml', [
        'url' => $url,
        'checks' => $checks,
    ]);
});

$app->post('/urls/{id}/checks', function (Request $request, Response $response, $args) {
    $pdo = $this->get('pdo');
    $id = $args['id'];

    $stmt = $pdo->prepare('SELECT name FROM urls WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $url = $stmt->fetchColumn();

    if (!$url) {
        $_SESSION['flash'] = 'Сайт не найден';
        return $response->withHeader('Location', "/urls/{$id}")->withStatus(302);
    }

    $client = new Client(['timeout' => 10]);

    try {
        $res = $client->request('GET', $url);
        $statusCode = $res->getStatusCode();
        $html = $res->getBody()->getContents();

        $doc = new Document($html);

        $title = ($el = $doc->first('title')) ? $el->text() : null;
        $h1 = ($el = $doc->first('h1')) ? $el->text() : null;
        $description = ($el = $doc->first('meta[name=description]')) ? $el->getAttribute('content') : null;

        $stmt = $pdo->prepare('
            INSERT INTO url_checks (url_id, status_code, title, h1, description, created_at)
            VALUES (:url_id, :status_code, :title, :h1, :description, NOW())
        ');
        $stmt->execute([
            'url_id' => $id,
            'status_code' => $statusCode,
            'title' => $title,
            'h1' => $h1,
            'description' => $description,
        ]);

        $_SESSION['flash'] = "Проверка выполнена. Код ответа: {$statusCode}";
    } catch (\Exception $e) {
        $_SESSION['flash'] = 'Ошибка при выполнении проверки';
    }

    return $response->withHeader('Location', "/urls/{$id}")->withStatus(302);
});

$app->get('/urls', function (Request $request, Response $response) {
    $pdo = $this->get('pdo');

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

    return $this->get('renderer')->render($response, 'urls/index.phtml', [
        'urls' => $urls,
    ]);
});
