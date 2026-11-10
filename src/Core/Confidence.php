<?php

declare(strict_types=1);

namespace Suisen\Core;

/**
 * 診断の信頼度（高・中・低）。外部に触らない計算だけを置く。
 *
 * A（根拠の量）＝ 聞いた項目の数 ÷ 全項目の数。チャット中も結果画面も使う。
 * B（差の大きさ）＝ 1位と2位の点数の差を、基準の差で割って 1 を上限にしたもの。結果画面だけで使う。
 */
final class Confidence
{
    public const HIGH = 'high';
    public const MEDIUM = 'medium';
    public const LOW = 'low';

    public function __construct(
        private readonly float $midRatio,
        private readonly float $highRatio,
        private readonly float $marginFull,
        private readonly float $evidenceWeight,
    ) {
    }

    /** チャット中の段階。根拠の量だけで決める。 */
    public function evidenceLevel(int $coveredTopics, int $totalTopics): string
    {
        return $this->levelOf($this->evidenceRatio($coveredTopics, $totalTopics));
    }

    /**
     * 結果画面の段階。
     *
     * @param bool $allAnswersSame 学生の値がすべて同じ（差が出ないので、必ず「低」）
     */
    public function resultLevel(int $coveredTopics, int $totalTopics, float $topScore, float $secondScore, bool $allAnswersSame): string
    {
        if ($allAnswersSame) {
            return self::LOW;
        }
        $margin = min(1.0, max(0.0, ($topScore - $secondScore) / $this->marginFull));
        $value = $this->evidenceWeight * $this->evidenceRatio($coveredTopics, $totalTopics)
            + (1.0 - $this->evidenceWeight) * $margin;
        return $this->levelOf($value);
    }

    /** 1位と2位の差が、基準以上に開いているか（結果画面の一言の理由に使う）。 */
    public function marginIsClear(float $topScore, float $secondScore): bool
    {
        return $topScore - $secondScore >= $this->marginFull;
    }

    private function evidenceRatio(int $coveredTopics, int $totalTopics): float
    {
        return $totalTopics <= 0 ? 0.0 : min(1.0, $coveredTopics / $totalTopics);
    }

    private function levelOf(float $value): string
    {
        return match (true) {
            $value >= $this->highRatio => self::HIGH,
            $value >= $this->midRatio => self::MEDIUM,
            default => self::LOW,
        };
    }
}
