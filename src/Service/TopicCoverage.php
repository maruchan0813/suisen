<?php

declare(strict_types=1);

namespace Suisen\Service;

use Suisen\Core\NextTopicPicker;
use Suisen\Policy;
use Suisen\Repository\MessageRepository;

/**
 * 会話で聞けた項目の数（信頼度の「根拠の量」）。チャット中も診断のときも同じ数え方にする。
 */
final class TopicCoverage
{
    public function __construct(
        private readonly MessageRepository $messages,
        private readonly Policy $policy,
    ) {
    }

    public function coveredCount(int $conversationId): int
    {
        $studentTexts = array_map(
            static fn (array $message): string => $message['content'],
            array_filter(
                $this->messages->listFor($conversationId),
                static fn (array $message): bool => $message['role'] === MessageRepository::ROLE_USER,
            ),
        );
        return count((new NextTopicPicker($this->policy->featureLabels()))
            ->coveredTopics($studentTexts, $this->messages->askedTopicsFor($conversationId)));
    }

    public function totalCount(): int
    {
        return count($this->policy->featureLabels());
    }
}
