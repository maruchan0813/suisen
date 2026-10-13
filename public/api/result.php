<?php

// 診断結果の画面（S5）に出す中身。

declare(strict_types=1);

use Suisen\Http\ApiHandler;

$app = require __DIR__ . '/../../src/bootstrap.php';

ApiHandler::runRead($app, true, function (int $userId) use ($app): array {
    $conversationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
    return $app->resultService()->resultOf($userId, $conversationId);
});
