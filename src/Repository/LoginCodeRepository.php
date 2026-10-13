<?php

declare(strict_types=1);

namespace Suisen\Repository;

use DateTimeImmutable;
use Suisen\Infra\Database;

final class LoginCodeRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function lastSentAt(string $email): ?DateTimeImmutable
    {
        $statement = $this->db->pdo()->prepare('SELECT MAX(created_at) FROM login_codes WHERE email = ?');
        $statement->execute([$email]);
        $sentAt = $statement->fetchColumn();
        return $sentAt === null || $sentAt === false ? null : new DateTimeImmutable($sentAt);
    }

    /** それまでの未使用のコードを使えなくしてから、新しいコードを保存する。 */
    public function replaceWithNew(string $email, string $codeHash, DateTimeImmutable $expiresAt, DateTimeImmutable $now): int
    {
        return $this->db->transaction(function () use ($email, $codeHash, $expiresAt, $now): int {
            $this->db->pdo()
                ->prepare('UPDATE login_codes SET used_at = ? WHERE email = ? AND used_at IS NULL')
                ->execute([Database::formatDateTime($now), $email]);
            $this->db->pdo()
                ->prepare('INSERT INTO login_codes (email, code_hash, expires_at, created_at) VALUES (?, ?, ?, ?)')
                ->execute([$email, $codeHash, Database::formatDateTime($expiresAt), Database::formatDateTime($now)]);
            return (int) $this->db->pdo()->lastInsertId();
        });
    }

    /** @return array{id: int, code_hash: string, failed_attempts: int, expires_at: string}|null */
    public function findLatestUnused(string $email): ?array
    {
        $statement = $this->db->pdo()->prepare(
            'SELECT id, code_hash, failed_attempts, expires_at FROM login_codes
             WHERE email = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1',
        );
        $statement->execute([$email]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    public function incrementFailedAttempts(int $id): void
    {
        $this->db->pdo()
            ->prepare('UPDATE login_codes SET failed_attempts = failed_attempts + 1 WHERE id = ?')
            ->execute([$id]);
    }

    public function markUsed(int $id, DateTimeImmutable $now): void
    {
        $this->db->pdo()
            ->prepare('UPDATE login_codes SET used_at = ? WHERE id = ?')
            ->execute([Database::formatDateTime($now), $id]);
    }
}
