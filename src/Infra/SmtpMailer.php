<?php

declare(strict_types=1);

namespace Suisen\Infra;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Gmail の SMTP（アプリパスワード）でメールを送る。
 */
final class SmtpMailer implements Mailer
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $user,
        private readonly string $appPassword,
        private readonly string $fromName,
    ) {
    }

    public function send(string $to, string $subject, string $body): void
    {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->Port = $this->port;
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Username = $this->user;
            $mail->Password = $this->appPassword;
            $mail->CharSet = PHPMailer::CHARSET_UTF8;

            $mail->setFrom($this->user, $this->fromName);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->isHTML(false);
            $mail->send();
        } catch (PHPMailerException $e) {
            throw new MailException('メールを送れませんでした：' . $e->getMessage(), 0, $e);
        }
    }
}
