<?php

declare(strict_types=1);

namespace Suisen\Repository;

use Suisen\Infra\Database;

final class MessageRepository
{
    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';

    public function __construct(private readonly Database $db)
    {
    }

    /** @param string|null $topic AI の返事で聞かせた項目の表示名 */
    public function add(int $conversationId, string $role, string $content, ?string $topic = null): void
    {
        $this->db->pdo()
            ->prepare('INSERT INTO messages (conversation_id, role, content, topic) VALUES (?, ?, ?, ?)')
            ->execute([$conversationId, $role, $content, $topic]);
    }

    /** @return list<string> その会話で、AI にもう聞かせた項目 */
    public function askedTopicsFor(int $conversationId): array
    {
        $statement = $this->db->pdo()->prepare(
            'SELECT DISTINCT topic FROM messages WHERE conversation_id = ? AND topic IS NOT NULL',
        );
        $statement->execute([$conversationId]);
        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** @return list<array{role: string, content: string}> 古い順 */
    public function listFor(int $conversationId): array
    {
        $statement = $this->db->pdo()->prepare(
            'SELECT role, content FROM messages WHERE conversation_id = ? ORDER BY id',
        );
        $statement->execute([$conversationId]);
        return $statement->fetchAll();
    }
}
