<?php

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

return function (App $app) {
    $app->get('/', function (Request $request, Response $response) {
        $flash = $this->get('flash');
        $messages = $flash->getMessages();

        $flashData = $messages['flash'][0] ?? null;

        return $this->get('renderer')->render($response, 'home.phtml', [
            'errors' => $messages['errors'][0] ?? [],
            'old' => $messages['old'][0] ?? [],
            'flash' => $flashData
        ]);
    })->setName('home');
};
