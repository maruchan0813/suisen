<?php

declare(strict_types=1);

use Suisen\Http\ApiHandler;

$app = require __DIR__ . '/../../src/bootstrap.php';

ApiHandler::run($app, true, function (array $body, int $userId) use ($app): array {
    $conversationId = $app->chatService()->start($userId);
    return ['conversation_id' => $conversationId, 'redirect' => "/chat/{$conversationId}"];
});
