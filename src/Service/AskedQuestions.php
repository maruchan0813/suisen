<?php

declare(strict_types=1);

namespace Suisen\Service;

use Suisen\Core\ReplyReview;
use Suisen\Repository\MessageRepository;

/**
 * これまでに AI がした質問を、会話の履歴から取り出す。
 * LLM は答えてもらえなかった質問に何度も戻ろうとするので、「もう聞いた質問」として見せる。
 */
final class AskedQuestions
{
    /**
     * @param list<array{role: string, content: string}> $history
     * @return list<string> 古い順。同じ質問は1つにまとめる
     */
    public static function from(array $history): array
    {
        $questions = [];
        foreach ($history as $message) {
            if ($message['role'] !== MessageRepository::ROLE_ASSISTANT) {
                continue;
            }
            array_push($questions, ...ReplyReview::questionsIn($message['content']));
        }
        return array_values(array_unique(array_filter($questions, static fn (string $question): bool => $question !== '')));
    }
}
