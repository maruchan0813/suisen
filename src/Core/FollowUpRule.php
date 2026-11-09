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
     * @param int $deepenEveryNthTopic 何項目に1回、深掘りするか（1なら毎回、2なら1つおき）。
     *        深掘りが続くと、学生が自分の相談を言い出せないため、間を空ける
     */
    public function __construct(
        private readonly int $maxFollowUps,
        private readonly int $minAnswerChars,
        private readonly array $skipPhrases,
        private readonly int $deepenEveryNthTopic = 1,
    ) {
    }

    /**
     * @param list<string|null> $assistantTopics AI の返事ごとの項目（古い順。項目について聞いていない返事は null）
     * @return string|null 深掘りする項目。深掘りしないときは null
     */
    public function topicToDeepen(array $assistantTopics, string $answer): ?string
    {
        $topic = $assistantTopics === [] ? null : end($assistantTopics);
        if ($topic === null || !$this->isWorthFollowing($answer) || !$this->isDeepeningTurn($assistantTopics)) {
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

    /** 項目を聞いた順に数えて、1番目、N+1番目…の項目だけ深掘りする。 */
    private function isDeepeningTurn(array $assistantTopics): bool
    {
        $topicsAsked = 0;
        $previous = null;
        foreach ($assistantTopics as $topic) {
            if ($topic !== null && $topic !== $previous) {
                $topicsAsked++;
            }
            $previous = $topic;
        }
        return ($topicsAsked - 1) % max(1, $this->deepenEveryNthTopic) === 0;
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
