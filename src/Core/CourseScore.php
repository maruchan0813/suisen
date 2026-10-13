<?php

declare(strict_types=1);

namespace Suisen\Core;

/**
 * 1つのコースの点数と、その内訳。
 */
final class CourseScore
{
    /**
     * @param array<string, float> $interestContributions 興味の項目ごとの寄与（大きいほど、学生とコースの両方で高い）
     */
    public function __construct(
        public readonly Course $course,
        public readonly float $total,
        public readonly float $interestScore,
        public readonly float $styleScore,
        public readonly array $interestContributions,
    ) {
    }

    /**
     * 推薦の根拠にする項目のキー。寄与が正のものだけを、大きい順に返す。
     *
     * @return list<string>
     */
    public function reasonKeys(int $limit): array
    {
        $positive = array_filter($this->interestContributions, static fn (float $value): bool => $value > 0.0);
        arsort($positive);
        return array_slice(array_keys($positive), 0, $limit);
    }
}
