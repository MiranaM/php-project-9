<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

$app = AppFactory::create();

$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write("Hello from Slim!");
    return $response;
});

$app->run();
