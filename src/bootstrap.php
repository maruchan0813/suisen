<?php

// Web の画面と API の入口で最初に読み込む。設定を読み、セッションを始め、App を返す。

declare(strict_types=1);

use Suisen\App;
use Suisen\Infra\Session;
use Suisen\Policy;

$rootDir = dirname(__DIR__);

if (!is_file($rootDir . '/vendor/autoload.php')) {
    http_response_code(500);
    exit('ライブラリがありません。SUISEN フォルダで composer install を実行してください。');
}
require $rootDir . '/vendor/autoload.php';

// エラーの中身（ファイルの場所など）を画面に出さず、ログにだけ書く。
// 警告も例外にして、API では「エラーが起きました」と JSON で返せるようにする。
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if (!is_file($rootDir . '/config/env.php')) {
    http_response_code(500);
    exit('config/env.php がありません。config/env.example.php をコピーして作ってください。');
}
$env = require $rootDir . '/config/env.php';

$policy = Policy::load($rootDir . '/policy');
date_default_timezone_set($policy->string('TIMEZONE'));
$session = Session::start($policy->int('SESSION_LIFETIME_SECONDS'), $rootDir . '/storage/sessions');

return new App($env, $policy, $session, $rootDir);
