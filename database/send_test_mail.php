<?php

// config/env.php のメール設定で、試しに1通だけ送る。
// 使い方：php database/send_test_mail.php 送り先のメールアドレス
// メール認証をオンにする前に、Gmail の設定（アプリパスワードなど）が正しいかを確かめるために使う。

declare(strict_types=1);

use Suisen\Infra\MailException;
use Suisen\Infra\SmtpMailer;

require __DIR__ . '/../vendor/autoload.php';

$to = $argv[1] ?? '';
if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "使い方：php database/send_test_mail.php 送り先のメールアドレス\n");
    exit(1);
}

$env = require dirname(__DIR__) . '/config/env.php';
if ($env['MAIL_DRIVER'] !== 'smtp' || $env['SMTP_APP_PASSWORD'] === '') {
    fwrite(STDERR, "config/env.php の MAIL_DRIVER を 'smtp' にし、SMTP_USER と SMTP_APP_PASSWORD を書いてください。\n");
    exit(1);
}

$mailer = new SmtpMailer(
    $env['SMTP_HOST'],
    (int) $env['SMTP_PORT'],
    $env['SMTP_USER'],
    $env['SMTP_APP_PASSWORD'],
    $env['MAIL_FROM_NAME'],
);

try {
    $mailer->send($to, '【SUISEN】テストメール', "SUISEN からのテストメールです。\nこのメールが届いていれば、メールの設定は正しく動いています。");
    echo "{$to} に送りました。受信箱（と迷惑メールのフォルダ）を確かめてください。\n";
} catch (MailException $e) {
    fwrite(STDERR, "送れませんでした：{$e->getMessage()}\n");
    fwrite(STDERR, "SMTP_USER とアプリパスワード（16文字、空白なし）が合っているか確かめてください。\n");
    exit(1);
}
