<?php

declare(strict_types=1);

use Suisen\Http\ApiHandler;

$app = require __DIR__ . '/../../src/bootstrap.php';

ApiHandler::run($app, false, function (array $body) use ($app): array {
    $app->loginService()->verifyCode(ApiHandler::string($body, 'code'), new DateTimeImmutable());
    return ['redirect' => '/home'];
});
