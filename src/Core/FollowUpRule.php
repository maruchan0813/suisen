<?php

declare(strict_types=1);

namespace Suisen\Core;

/**
 * 学生の答えを、同じ項目でもう少し聞く（深掘り）かを決める。
 * 毎回新しい項目に移ると、話を聞いてもらえていないように見えるため、
 * 話が広がりそうな答えのときだけ、同じ項目を決まった回数まで続けて聞く。
 */
final class FollowUpRule
{
    /**
     * @param int $maxFollowUps 1つの項目を深掘りする回数
     * @param int $minAnswerChars これより短い答えは深掘りしない
     * @param list<string> $skipPhrases これを含む答えは深掘りしない
     */
    public function __construct(
        private readonly int $maxFollowUps,
        private readonly int $minAnswerChars,
        private readonly array $skipPhrases,
    ) {
    }

    /**
     * @param list<string|null> $assistantTopics AI の返事ごとの項目（古い順。項目について聞いていない返事は null）
     * @return string|null 深掘りする項目。深掘りしないときは null
     */
    public function topicToDeepen(array $assistantTopics, string $answer): ?string
    {
        $topic = $assistantTopics === [] ? null : end($assistantTopics);
        if ($topic === null || !$this->isWorthFollowing($answer)) {
            return null;
        }
        // 末尾から続く同じ項目の返事の数 = 最初に聞いた1回 + これまでの深掘り
        $count = 0;
        foreach (array_reverse($assistantTopics) as $past) {
            if ($past !== $topic) {
                break;
            }
            $count++;
        }
        return $count <= $this->maxFollowUps ? $topic : null;
    }

    private function isWorthFollowing(string $answer): bool
    {
        if (mb_strlen(trim($answer)) < $this->minAnswerChars) {
            return false;
        }
        foreach ($this->skipPhrases as $phrase) {
            if (str_contains($answer, $phrase)) {
                return false;
            }
        }
        return true;
    }
}
