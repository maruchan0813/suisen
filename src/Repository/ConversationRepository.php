<?php

declare(strict_types=1);

namespace Suisen\Repository;

use Suisen\Infra\Database;

final class ConversationRepository
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DIAGNOSED = 'diagnosed';

    public function __construct(private readonly Database $db)
    {
    }

    public function create(int $userId): int
    {
        $this->db->pdo()->prepare('INSERT INTO conversations (user_id) VALUES (?)')->execute([$userId]);
        return (int) $this->db->pdo()->lastInsertId();
    }

    /**
     * その利用者の会話だけを返す（他人の会話は null）。
     *
     * @return array{id: int, status: string}|null
     */
    public function findOwned(int $conversationId, int $userId): ?array
    {
        $statement = $this->db->pdo()->prepare('SELECT id, status FROM conversations WHERE id = ? AND user_id = ?');
        $statement->execute([$conversationId, $userId]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    public function latestActiveId(int $userId): ?int
    {
        $statement = $this->db->pdo()->prepare(
            'SELECT id FROM conversations WHERE user_id = ? AND status = ? ORDER BY id DESC LIMIT 1',
        );
        $statement->execute([$userId, self::STATUS_ACTIVE]);
        $id = $statement->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function markDiagnosed(int $conversationId): void
    {
        $this->db->pdo()
            ->prepare('UPDATE conversations SET status = ? WHERE id = ?')
            ->execute([self::STATUS_DIAGNOSED, $conversationId]);
    }
}
