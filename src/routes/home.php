<?php

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

return function (App $app) {
    $app->get('/', function (Request $request, Response $response) {
        $errors = $_SESSION['errors'] ?? [];
        $old = $_SESSION['old'] ?? [];
        $flash = $_SESSION['flash'] ?? null;

        unset($_SESSION['errors'], $_SESSION['old'], $_SESSION['flash']);

        return $this->get('renderer')->render($response, 'home.phtml', [
            'errors' => $errors,
            'old' => $old,
            'flash' => $flash
        ]);
    })->setName('home');
};
