<?php

// ホームに出す、その時点の利用者のステータス。

declare(strict_types=1);

use Suisen\Http\ApiHandler;

$app = require __DIR__ . '/../../src/bootstrap.php';

ApiHandler::runRead($app, true, function (int $userId) use ($app): array {
    return $app->statusService()->statusOf($userId);
});
