<?php

declare(strict_types=1);

namespace Suisen\Infra;

/**
 * ログイン状態、確認コード待ちのメールアドレス、CSRF トークンを PHP のセッションで持つ。
 */
final class Session
{
    private const NAME = 'SUISEN_SESSION';
    private const KEY_USER_ID = 'user_id';
    private const KEY_LOGGED_IN_AT = 'logged_in_at';
    private const KEY_PENDING_EMAIL = 'pending_email';
    private const KEY_CSRF_TOKEN = 'csrf_token';
    private const CSRF_TOKEN_BYTES = 32;

    private function __construct(private readonly int $lifetimeSeconds)
    {
    }

    /**
     * 設定は php.ini ではなくここで行う（MAMP と XAMPP で php.ini を直さずに済むように）。
     * 保存先はアプリ専用にする（共通の保存先だと、他のアプリの設定で早く消されることがあるため）。
     */
    public static function start(int $lifetimeSeconds, string $savePath): self
    {
        if (!is_dir($savePath)) {
            mkdir($savePath, 0700, true);
        }
        ini_set('session.gc_maxlifetime', (string) $lifetimeSeconds);
        ini_set('session.use_strict_mode', '1');
        session_save_path($savePath);
        session_name(self::NAME);
        session_set_cookie_params([
            'lifetime' => $lifetimeSeconds,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        return new self($lifetimeSeconds);
    }

    /** ログイン中の利用者の id。未ログインか期限切れなら null。 */
    public function userId(): ?int
    {
        if (!isset($_SESSION[self::KEY_USER_ID], $_SESSION[self::KEY_LOGGED_IN_AT])) {
            return null;
        }
        if (time() - $_SESSION[self::KEY_LOGGED_IN_AT] > $this->lifetimeSeconds) {
            $this->logout();
            return null;
        }
        return (int) $_SESSION[self::KEY_USER_ID];
    }

    public function login(int $userId): void
    {
        session_regenerate_id(true); // ログイン前のセッション ID を使い回させない
        $_SESSION[self::KEY_USER_ID] = $userId;
        $_SESSION[self::KEY_LOGGED_IN_AT] = time();
        unset($_SESSION[self::KEY_PENDING_EMAIL]);
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public function pendingEmail(): ?string
    {
        return $_SESSION[self::KEY_PENDING_EMAIL] ?? null;
    }

    public function setPendingEmail(string $email): void
    {
        $_SESSION[self::KEY_PENDING_EMAIL] = $email;
    }

    public function csrfToken(): string
    {
        if (empty($_SESSION[self::KEY_CSRF_TOKEN])) {
            $_SESSION[self::KEY_CSRF_TOKEN] = bin2hex(random_bytes(self::CSRF_TOKEN_BYTES));
        }
        return $_SESSION[self::KEY_CSRF_TOKEN];
    }

    public function isValidCsrfToken(mixed $token): bool
    {
        return is_string($token) && hash_equals($this->csrfToken(), $token);
    }
}
