<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Tueen\Telegram\App;

// Create Tueen Bot App instance and handle incoming HTTP request
$app = App::create(dirname(__DIR__));
$app->run();
