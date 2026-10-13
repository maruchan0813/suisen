<?php

declare(strict_types=1);

use Suisen\Http\ApiHandler;

$app = require __DIR__ . '/../../src/bootstrap.php';

ApiHandler::run($app, true, function () use ($app): array {
    $app->loginService()->logout();
    return ['redirect' => '/'];
});
