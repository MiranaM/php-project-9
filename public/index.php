<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

// Настройка шаблонизатора
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// Главная страница
$app->get('/', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../templates');
    
    // Рендерим контент страницы
    $content = $renderer->fetch('index.phtml', [
        'title' => 'Анализатор страниц',
        'description' => 'Бесплатно проверяйте сайты на SEO-пригодность'
    ]);
    
    // Рендерим layout с контентом
    return $renderer->render($response, 'layouts/base.phtml', [
        'title' => 'Анализатор страниц',
        'content' => $content
    ]);
});

$app->run();
