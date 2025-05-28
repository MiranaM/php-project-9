<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;

$app = AppFactory::create();

// Шаблонизатор указывает папку с шаблонами
$renderer = new PhpRenderer(__DIR__ . '/../templates');

$app->get('/', function ($request, $response) use ($renderer) {
    return $renderer->render($response, 'home.phtml');
});

$app->run();
