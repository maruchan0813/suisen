<?php

declare(strict_types=1);

namespace Suisen\Service;

use Suisen\AppException;
use Suisen\Policy;
use Suisen\Repository\ConversationRepository;
use Suisen\Repository\MessageRepository;

/**
 * 会話を扱う前の確認と、往復数の数え方。ChatService と DiagnosisService で共通の決まり。
 */
final class ConversationAccess
{
    public function __construct(
        private readonly ConversationRepository $conversations,
        private readonly Policy $policy,
    ) {
    }

    /**
     * ログイン中の利用者の会話かを確かめる。他人の会話は「ない」ことにする。
     *
     * @return array{id: int, status: string}
     */
    public function requireOwned(int $userId, int $conversationId): array
    {
        return $this->conversations->findOwned($conversationId, $userId)
            ?? throw new AppException('not_found', 404);
    }

    /** @return array{id: int, status: string} */
    public function requireActive(int $userId, int $conversationId): array
    {
        $conversation = $this->requireOwned($userId, $conversationId);
        if ($conversation['status'] !== ConversationRepository::STATUS_ACTIVE) {
            throw new AppException('conversation_closed', 409);
        }
        return $conversation;
    }

    /**
     * 往復数＝学生が送ったメッセージの数。
     *
     * @param list<array{role: string, content: string}> $messages
     */
    public static function countTurns(array $messages): int
    {
        return count(array_filter(
            $messages,
            static fn (array $message): bool => $message['role'] === MessageRepository::ROLE_USER,
        ));
    }

    public function canDiagnose(int $turnCount): bool
    {
        return $turnCount >= $this->policy->int('MIN_TURNS_BEFORE_DIAGNOSIS');
    }
}
