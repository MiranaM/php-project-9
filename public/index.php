<?php

require_once __DIR__ . '/../vendor/autoload.php';

session_start();

try {
    $app = require_once __DIR__ . '/../app/config/bootstrap.php';
} catch (\Throwable $e) {
    $statusCode = 500;
    
    $content = '<p class="lead">Проверьте переменную окружения <code>DATABASE_URL</code> или параметры подключения</p>';

    ob_start();
    include_once __DIR__ . '/../../templates/layout.phtml';
    $html = ob_get_clean();

    http_response_code($statusCode);
    echo $html;
    exit;
}

$app->run();
