<?php

declare(strict_types=1);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

session_start();

$app = require __DIR__ . '/../src/config/bootstrap.php';
$app->run();
