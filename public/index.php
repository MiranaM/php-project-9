<?php

require __DIR__ . '/../vendor/autoload.php';

session_start();

try {
    $app = require __DIR__ . '/../app/config/bootstrap.php';
} catch (\Throwable $e) {
    $statusCode = 500;
    $title = 'Ошибка подключения к базе данных';
    $content = '<p class="lead">Проверьте переменную окружения <code>DATABASE_URL</code> или параметры подключения</p>';

    ob_start();
    include __DIR__ . '/../../templates/layout.phtml';
    $html = ob_get_clean();

    http_response_code($statusCode);
    echo $html;
    exit;
}

$app->run();
