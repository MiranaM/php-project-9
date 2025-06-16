<?php

require __DIR__ . '/../vendor/autoload.php';

session_start();

$app = require __DIR__ . '/../src/bootstrap.php';
$app->run();
