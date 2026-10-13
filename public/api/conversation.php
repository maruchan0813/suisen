<?php

// チャットの画面に出す、会話の中身。

declare(strict_types=1);

use Suisen\Http\ApiHandler;

$app = require __DIR__ . '/../../src/bootstrap.php';

ApiHandler::runRead($app, true, function (int $userId) use ($app): array {
    $conversationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
    return $app->chatService()->open($userId, $conversationId);
});
