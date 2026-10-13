<?php

declare(strict_types=1);

use Suisen\Http\ApiHandler;

$app = require __DIR__ . '/../../src/bootstrap.php';

ApiHandler::run($app, true, function (array $body, int $userId) use ($app): array {
    return $app->chatService()->send(
        $userId,
        ApiHandler::int($body, 'conversation_id'),
        ApiHandler::string($body, 'content'),
    );
});
