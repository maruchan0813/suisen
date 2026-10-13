<?php

declare(strict_types=1);

namespace Suisen\Infra;

/**
 * メールを送らずにファイルへ書く。Gmail の準備ができるまでの動作確認用。
 */
final class LogMailer implements Mailer
{
    public function __construct(private readonly string $logFile)
    {
    }

    public function send(string $to, string $subject, string $body): void
    {
        $entry = sprintf("==== %s\nTo: %s\nSubject: %s\n\n%s\n\n", date('c'), $to, $subject, $body);
        if (file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX) === false) {
            throw new MailException("{$this->logFile} に書けませんでした。");
        }
    }
}
