<?php

use Slim\App;
use Slim\Psr7\Response;
use Slim\Psr7\Request;
use Psr\Container\ContainerInterface;
use DiDom\Document;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use Valitron\Validator;
use Slim\Routing\RouteContext;

return function (App $app): void {
    /** @var ContainerInterface $container */
    $container = $app->getContainer();

    $app->get('/urls/{id}', function (Request $request, Response $response, $args) use ($container) {
        $pdo = $container->get('pdo');
        $id = $args['id'];

        $stmt = $pdo->prepare('SELECT * FROM urls WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $url = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$url) {
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        if (isset($url['created_at'])) {
            $url['created_at'] = substr($url['created_at'], 0, 19);
        }

        $stmt = $pdo->prepare('
            SELECT *
            FROM url_checks
            WHERE url_id = :url_id
            ORDER BY created_at DESC
        ');
        $stmt->execute(['url_id' => $id]);
        $checks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($checks as &$check) {
            if (isset($check['created_at'])) {
                $check['created_at'] = substr($check['created_at'], 0, 19);
            }
        }

        $flash = $container->get('flash');
        $messages = $flash->getMessages();
        $flashData = $messages['flash'][0] ?? null;

        return $container->get('renderer')->render($response, 'urls/show.phtml', [
            'url' => $url,
            'checks' => $checks,
            'flash' => $flashData,
        ]);
    })->setName('urls.show');

    $app->post('/urls/{id}/checks', function (Request $request, Response $response, $args) use ($container) {
        $pdo = $container->get('pdo');
        $id = $args['id'];
        $flash = $container->get('flash');
        $messages = $flash->getMessages();
        $flashData = $messages['flash'][0] ?? null;

        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        $stmt = $pdo->prepare('SELECT name FROM urls WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $url = $stmt->fetchColumn();

        if (!$url) {
            $flash->addMessage('flash', ['type' => 'danger', 'message' => 'Сайт не найден']);
            $path = $routeParser->urlFor('urls.show', ['id' => $id]);
            return $response->withHeader('Location', $path)->withStatus(302);
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
                'description' => $description,
            ]);

            $flash->addMessage('flash', ['type' => 'success', 'message' => 'Страница успешно проверена']);
        } catch (ConnectException $e) {
            $flash->addMessage('flash', ['type' => 'danger', 'message' => 'Не удалось подключиться к сайту']);
        } catch (RequestException $e) {
            $flash->addMessage('flash', ['type' => 'danger', 'message' => 'Ошибка при выполнении запроса: ' . $e->getMessage()]);
        }

        $path = $routeParser->urlFor('urls.show', ['id' => $id]);
        return $response->withHeader('Location', $path)->withStatus(302);
    })->setName('urls.check');

    $app->get('/urls', function (Request $request, Response $response) use ($container) {
        $pdo = $container->get('pdo');
        $urlsStmt = $pdo->query('SELECT * FROM urls ORDER BY id DESC');
        $urls = $urlsStmt->fetchAll(PDO::FETCH_ASSOC);

        $checksStmt = $pdo->query("
            SELECT url_id, MAX(created_at) AS last_check
            FROM url_checks
            GROUP BY url_id
        ");
        $lastChecks = $checksStmt->fetchAll(PDO::FETCH_ASSOC);

        $statusStmt = $pdo->query("
            SELECT uc.url_id, uc.status_code, uc.created_at
            FROM url_checks uc
            INNER JOIN (
                SELECT url_id, MAX(created_at) AS last_check
                FROM url_checks
                GROUP BY url_id
            ) latest ON uc.url_id = latest.url_id AND uc.created_at = latest.last_check
        ");

        $statuses = [];
        foreach ($statusStmt as $row) {
            $statuses[$row['url_id']] = [
                'last_status' => $row['status_code'],
                'last_check' => $row['created_at']
            ];
        }

        foreach ($urls as &$url) {
            $urlId = $url['id'];
            $url['last_status'] = $statuses[$urlId]['last_status'] ?? null;
            $url['last_check'] = $statuses[$urlId]['last_check'] ?? null;
            if ($url['last_check']) {
                $url['last_check'] = substr($url['last_check'], 0, 19);
            }
        }

        return $container->get('renderer')->render($response, 'urls/index.phtml', [
            'urls' => $urls,
        ]);
    })->setName('urls.index');

    $app->post('/urls', function (Request $request, Response $response) use ($container) {
        $pdo = $container->get('pdo');
        $flash = $container->get('flash');
        $renderer = $container->get('renderer');
        $messages = $flash->getMessages();
        $flashData = $messages['flash'][0] ?? null;

        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        $data = $request->getParsedBody();
        $data = is_array($data) ? $data : [];
        $data = $data['url'] ?? [];
        $url = trim($data['name'] ?? '');

        $v = new Validator(['name' => $url]);
        $v->labels(['name' => 'URL']);
        $v->rule('required', 'name')->message('URL не должен быть пустым');
        $v->rule('url', 'name')->message('Некорректный URL');
        $v->rule('lengthMax', 'name', 255)->message('URL не должен превышать 255 символов');

        if (!$v->validate()) {
            $errors = $v->errors();
            $old = $data;
            return $renderer->render($response->withStatus(422), 'home.phtml', [
                'errors' => $errors,
                'old' => $old,
            ]);
        }

        $parsed = parse_url($url);
        $normalizedUrl = "{$parsed['scheme']}://{$parsed['host']}";

        $stmt = $pdo->prepare('SELECT id FROM urls WHERE name = :name');
        $stmt->execute(['name' => $normalizedUrl]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            $stmt = $pdo->prepare('INSERT INTO urls (name, created_at) VALUES (:name, NOW())');
            $stmt->execute(['name' => $normalizedUrl]);
            $urlId = $pdo->lastInsertId();
            $flash->addMessage('flash', ['type' => 'success', 'message' => 'Страница успешно добавлена']);
        } else {
            $urlId = $existing['id'];
            $flash->addMessage('flash', ['type' => 'danger', 'message' => 'Страница уже существует']);
        }

        $path = $routeParser->urlFor('urls.show', ['id' => $urlId]);
        return $response->withHeader('Location', $path)->withStatus(302);
    })->setName('urls.store');
};
