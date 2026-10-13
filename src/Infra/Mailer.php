<?php

declare(strict_types=1);

namespace Suisen\Infra;

/**
 * メールを送る。本番は SmtpMailer、Gmail の準備ができるまでの動作確認は LogMailer。
 */
interface Mailer
{
    /** @throws MailException */
    public function send(string $to, string $subject, string $body): void;
}
