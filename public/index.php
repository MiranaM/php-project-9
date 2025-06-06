<?php

require __DIR__ . '/../vendor/autoload.php';

session_start();

$app = require __DIR__ . '/../app/config/bootstrap.php';

require __DIR__ . '/../app/routes/home.php';
require __DIR__ . '/../app/routes/urls.php';

$app->run();
