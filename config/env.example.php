<?php
// 環境設定のひな形。config/env.php にコピーして、動かす PC に合わせて書き換える。
// env.php はパスワードを含むので共有しない。

return [
    // MySQL
    // Mac / MAMP   : ポート 8889（3306 に変えられていることもある）、パスワード root
    // Windows / XAMPP: ポート 3306、パスワード 空
    // 実際のポートは推測せず、MAMP・XAMPP の画面で確かめる。
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => 8889,
    'DB_NAME' => 'suisen',
    'DB_USER' => 'root',
    'DB_PASS' => 'root',

    // 北九州高専 学内 LLM（学内ネットワークからのみ）
    'LLM_BASE_URL' => 'http://10.201.24.24:8200/v1',
    'LLM_API_KEY'  => 'local-llm-key',
    'LLM_MODEL'    => 'qwen3.8-27b',

    // メール送信
    // 'smtp': Gmail から本当に送る。
    // 'log' : 送らずに storage/mail.log に書く（Gmail の準備ができるまでの動作確認用）。
    'MAIL_DRIVER'       => 'log',
    'SMTP_HOST'         => 'smtp.gmail.com',
    'SMTP_PORT'         => 587,
    'SMTP_USER'         => 'your-app-account@gmail.com',
    'SMTP_APP_PASSWORD' => '',
    'MAIL_FROM_NAME'    => 'SUISEN',
];
