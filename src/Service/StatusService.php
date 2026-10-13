<?php

declare(strict_types=1);

namespace Suisen\Service;

use Suisen\Repository\ConversationRepository;
use Suisen\Repository\DiagnosisRepository;

/**
 * ホームに出す、その時点の利用者のステータス。
 */
final class StatusService
{
    public function __construct(
        private readonly DiagnosisRepository $diagnoses,
        private readonly ConversationRepository $conversations,
    ) {
    }

    /**
     * @return array{course_name: ?string, diagnosed_at: ?string, diagnosis_count: int, active_conversation_id: ?int, latest_result_conversation_id: ?int}
     */
    public function statusOf(int $userId): array
    {
        $latest = $this->diagnoses->latestFor($userId);
        return [
            'course_name'            => $latest['course_name'] ?? null,
            'diagnosed_at'           => $latest['created_at'] ?? null,
            'diagnosis_count'        => $this->diagnoses->countFor($userId),
            'active_conversation_id' => $this->conversations->latestActiveId($userId),
            'latest_result_conversation_id' => $this->diagnoses->latestConversationIdFor($userId),
        ];
    }
}
