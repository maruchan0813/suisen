<?php

// メール認証が無効なときの、メールアドレスだけでのログイン。
// 設定を切り替える前に開いた画面からも呼ばれるので、メール認証が有効なら確認コードを送って、コード入力へ進める。

declare(strict_types=1);

use Suisen\Http\ApiHandler;

$app = require __DIR__ . '/../../src/bootstrap.php';

ApiHandler::run($app, false, function (array $body) use ($app): array {
    $loginService = $app->loginService();
    $email = ApiHandler::string($body, 'email');
    if ($loginService->isVerificationEnabled()) {
        $loginService->sendCode($email, new DateTimeImmutable());
        return ['redirect' => '/verify'];
    }
    $loginService->loginWithoutVerification($email);
    return ['redirect' => '/home'];
});
