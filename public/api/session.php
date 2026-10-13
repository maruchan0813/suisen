<?php

// 画面を開いたときに読む、ログイン状態と CSRF トークン。

declare(strict_types=1);

use Suisen\Http\ApiHandler;

$app = require __DIR__ . '/../../src/bootstrap.php';

ApiHandler::runRead($app, false, function () use ($app): array {
    return [
        'logged_in'             => $app->session->userId() !== null,
        'pending_email'         => $app->session->pendingEmail(),
        'csrf_token'            => $app->session->csrfToken(),
        'verification_enabled'  => $app->policy->bool('EMAIL_VERIFICATION_ENABLED'),
        'code_digits'           => $app->policy->int('LOGIN_CODE_DIGITS'),
        'network_error_message' => $app->policy->message('network_error'),
    ];
});
