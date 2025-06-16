<?php

use Slim\App;
use Slim\Psr7\Response;
use Slim\Psr7\Request;
use Slim\Routing\RouteContext;
use DiDom\Document;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;

return function (App $app) {
    $app->get('/urls', function (Request $request, Response $response) {
        $pdo = $this->get('pdo');

        $stmt = $pdo->query('SELECT * FROM urls ORDER BY id DESC');
        $urls = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $urlChecks = $pdo->query('
            SELECT DISTINCT ON (url_id) *
            FROM url_checks
            ORDER BY url_id, created_at DESC
        ')->fetchAll(PDO::FETCH_ASSOC);

        $checksIndex = [];
        foreach ($urlChecks as $check) {
            $checksIndex[$check['url_id']] = $check;
        }

        return $this->get('renderer')->render($response, 'urls/index.phtml', [
            'urls' => $urls,
            'checksIndex' => $checksIndex,
            'flash' => $_SESSION['flash'] ?? null,
        ]);
    })->setName('urls.index');

    $app->get('/urls/{id}', function (Request $request, Response $response, array $args) {
        $pdo = $this->get('pdo');
        $id = $args['id'];

        $stmt = $pdo->prepare('SELECT * FROM urls WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $url = $stmt->fetch();

        if (!$url) {
            $response->getBody()->write('URL не найден');
            return $response->withStatus(404);
        }

        $stmt = $pdo->prepare('SELECT * FROM url_checks WHERE url_id = :url_id ORDER BY created_at DESC');
        $stmt->execute(['url_id' => $id]);
        $checks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->get('renderer')->render($response, 'urls/show.phtml', [
            'url' => $url,
            'checks' => $checks,
            'flash' => $_SESSION['flash'] ?? null,
        ]);
    })->setName('urls.show');

    $app->post('/urls/{id}/checks', function (Request $request, Response $response, array $args) {
        $pdo = $this->get('pdo');
        $id = $args['id'];
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        $stmt = $pdo->prepare('SELECT name FROM urls WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $url = $stmt->fetchColumn();

        if (!$url) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Сайт не найден'];
            return $response->withHeader('Location', $routeParser->urlFor('urls.index'))->withStatus(302);
        }

        $client = new Client(['timeout' => 10]);

        try {
            $res = $client->request('GET', $url);
            $statusCode = $res->getStatusCode();
            $html = $res->getBody()->getContents();
            $doc = new Document($html);

            $title = $doc->first('title')?->text() ?? null;
            $h1 = $doc->first('h1')?->text() ?? null;
            $description = $doc->first('meta[name=description]')?->getAttribute('content') ?? null;

            $stmt = $pdo->prepare('
                INSERT INTO url_checks (url_id, status_code, title, h1, description, created_at)
                VALUES (:url_id, :status_code, :title, :h1, :description, NOW())
            ');
            $stmt->execute([
                'url_id' => $id,
                'status_code' => $statusCode,
                'title' => $title,
                'h1' => $h1,
                'description' => $description
            ]);

            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Проверка выполнена успешно'];
        } catch (ConnectException | RequestException $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Не удалось выполнить проверку: ' . $e->getMessage()];
        }

        return $response
            ->withHeader('Location', $routeParser->urlFor('urls.show', ['id' => $id]))
            ->withStatus(302);
    })->setName('urls.checks');

    $app->post('/urls', function (Request $request, Response $response) {
        $pdo = $this->get('pdo');
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        $data = $request->getParsedBody()['url'] ?? [];
        $name = trim($data['name'] ?? '');

        $_SESSION['old'] = ['name' => $name];

        if (!filter_var($name, FILTER_VALIDATE_URL)) {
            $_SESSION['errors'] = ['name' => ['Некорректный URL']];
            return $response
                ->withHeader('Location', $routeParser->urlFor('home'))
                ->withStatus(302);
        }

        $parsed = parse_url($name);
        if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Невалидный адрес'];
            return $response
                ->withHeader('Location', $routeParser->urlFor('home'))
                ->withStatus(302);
        }

        $normalizedUrl = "{$parsed['scheme']}://{$parsed['host']}";

        $stmt = $pdo->prepare('SELECT id FROM urls WHERE name = :name');
        $stmt->execute(['name' => $normalizedUrl]);
        $existing = $stmt->fetch();

        if ($existing) {
            unset($_SESSION['old']);
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Страница уже существует'];
            $urlId = $existing['id'];
        } else {
            $stmt = $pdo->prepare('INSERT INTO urls (name, created_at) VALUES (:name, NOW())');
            $stmt->execute(['name' => $normalizedUrl]);
            $urlId = $pdo->lastInsertId();
            unset($_SESSION['old']);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Страница успешно добавлена'];
        }

        return $response
            ->withHeader('Location', $routeParser->urlFor('urls.show', ['id' => $urlId]))
            ->withStatus(302);
    })->setName('urls.store');
};
