<?php

declare(strict_types=1);

namespace Suisen\Service;

use Suisen\AppException;
use Suisen\Core\Confidence;
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
        private readonly Confidence $confidence,
        private readonly TopicCoverage $coverage,
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

    /**
     * 診断できるか。情報量が「中」以上になったら。往復数の上限まで話したときは、情報量が低くても診断できる
     * （上限に達すると、これ以上話せないため）。
     */
    public function canDiagnose(int $conversationId, int $turnCount): bool
    {
        if ($turnCount >= $this->policy->int('MAX_TURNS_PER_CONVERSATION')) {
            return true;
        }
        $level = $this->confidence->evidenceLevel(
            $this->coverage->coveredCount($conversationId),
            $this->coverage->totalCount(),
        );
        return $level !== Confidence::LOW;
    }
}
