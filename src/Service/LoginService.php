<?php

declare(strict_types=1);

namespace Suisen\Service;

use DateTimeImmutable;
use Suisen\AppException;
use Suisen\Infra\Mailer;
use Suisen\Infra\MailException;
use Suisen\Infra\Session;
use Suisen\Policy;
use Suisen\Repository\LoginCodeRepository;
use Suisen\Repository\UserRepository;

/**
 * 学校のメールアドレスと確認コードでログインする。
 */
final class LoginService
{
    private const SECONDS_PER_MINUTE = 60;

    public function __construct(
        private readonly UserRepository $users,
        private readonly LoginCodeRepository $codes,
        private readonly Mailer $mailer,
        private readonly Session $session,
        private readonly Policy $policy,
    ) {
    }

    /**
     * 確認コードなしで、メールアドレスだけでログインする。
     * メール認証が有効なときは使えない（確認を飛ばされないように）。
     *
     * @return int ログインした利用者の id
     */
    public function loginWithoutVerification(string $rawEmail): int
    {
        if ($this->isVerificationEnabled()) {
            throw new AppException('not_found', 404);
        }
        return $this->completeLogin($this->normalizeEmail($rawEmail));
    }

    public function isVerificationEnabled(): bool
    {
        return $this->policy->bool('EMAIL_VERIFICATION_ENABLED');
    }

    public function sendCode(string $rawEmail, DateTimeImmutable $now): void
    {
        if (!$this->isVerificationEnabled()) {
            throw new AppException('not_found', 404);
        }
        $email = $this->normalizeEmail($rawEmail);
        $this->assertResendAllowed($email, $now);

        $code = $this->generateCode();
        $expiresAt = $now->modify(sprintf('+%d seconds', $this->policy->int('LOGIN_CODE_TTL_SECONDS')));
        $codeId = $this->codes->replaceWithNew($email, password_hash($code, PASSWORD_DEFAULT), $expiresAt, $now);

        try {
            $this->mailer->send($email, $this->policy->message('mail_subject'), $this->mailBody($code));
        } catch (MailException $e) {
            error_log((string) $e);
            $this->codes->markUsed($codeId, $now); // 届いていないコードは使えなくする
            throw new AppException('mail_failed', 502, previous: $e);
        }

        $this->session->setPendingEmail($email);
    }

    /** @return int ログインした利用者の id */
    public function verifyCode(string $rawCode, DateTimeImmutable $now): int
    {
        $email = $this->session->pendingEmail() ?? throw new AppException('no_pending_email');

        $loginCode = $this->codes->findLatestUnused($email);
        if ($loginCode === null || new DateTimeImmutable($loginCode['expires_at']) < $now) {
            throw new AppException('code_expired');
        }

        $maxAttempts = $this->policy->int('LOGIN_CODE_MAX_ATTEMPTS');
        $failedAttempts = (int) $loginCode['failed_attempts'];
        if ($failedAttempts >= $maxAttempts) {
            throw new AppException('too_many_attempts');
        }

        if (!password_verify(trim($rawCode), $loginCode['code_hash'])) {
            $this->codes->incrementFailedAttempts((int) $loginCode['id']);
            $remaining = $maxAttempts - $failedAttempts - 1;
            throw $remaining > 0
                ? new AppException('wrong_code', params: ['remaining' => $remaining])
                : new AppException('too_many_attempts');
        }

        $this->codes->markUsed((int) $loginCode['id'], $now);
        return $this->completeLogin($email);
    }

    public function logout(): void
    {
        $this->session->logout();
    }

    /** 利用者がいなければ登録し、ログイン状態にする。 */
    private function completeLogin(string $email): int
    {
        $userId = $this->users->findOrCreate($email);
        $this->session->login($userId);
        return $userId;
    }

    private function normalizeEmail(string $rawEmail): string
    {
        $email = strtolower(trim($rawEmail));
        $domain = '@' . $this->policy->string('ALLOWED_EMAIL_DOMAIN');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || !str_ends_with($email, $domain)) {
            throw new AppException('invalid_email');
        }
        return $email;
    }

    private function assertResendAllowed(string $email, DateTimeImmutable $now): void
    {
        $lastSentAt = $this->codes->lastSentAt($email);
        $interval = $this->policy->int('LOGIN_CODE_RESEND_INTERVAL_SECONDS');
        if ($lastSentAt !== null && $now->getTimestamp() - $lastSentAt->getTimestamp() < $interval) {
            throw new AppException('send_too_soon', 429);
        }
    }

    private function generateCode(): string
    {
        $digits = $this->policy->int('LOGIN_CODE_DIGITS');
        return str_pad((string) random_int(0, 10 ** $digits - 1), $digits, '0', STR_PAD_LEFT);
    }

    private function mailBody(string $code): string
    {
        return $this->policy->message('mail_body', [
            'code'    => $code,
            'minutes' => intdiv($this->policy->int('LOGIN_CODE_TTL_SECONDS'), self::SECONDS_PER_MINUTE),
        ]);
    }
}
