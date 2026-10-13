<?php

declare(strict_types=1);

namespace Suisen\Core;

use InvalidArgumentException;

/**
 * 数値の並び（ベクトル）どうしの近さを求める。外部には触らない。
 */
final class Similarity
{
    /** @param list<float> $values */
    public static function mean(array $values): float
    {
        if ($values === []) {
            throw new InvalidArgumentException('空の配列の平均は求められません。');
        }
        return array_sum($values) / count($values);
    }

    /**
     * 平均との差。
     *
     * @param list<float> $values
     * @return list<float>
     */
    public static function centered(array $values): array
    {
        $mean = self::mean($values);
        return array_map(static fn (float $value): float => $value - $mean, $values);
    }

    /**
     * 相関係数（-1〜1）。平均との差どうしのコサイン類似度と同じ。
     * 片方のばらつきが0なら、近さを決められないので0を返す。
     *
     * @param list<float> $a
     * @param list<float> $b
     */
    public static function pearson(array $a, array $b): float
    {
        self::assertSameLength($a, $b);
        return self::cosine(self::centered($a), self::centered($b));
    }

    /**
     * コサイン類似度（-1〜1）。片方の長さが0なら0を返す。
     *
     * @param list<float> $a
     * @param list<float> $b
     */
    public static function cosine(array $a, array $b): float
    {
        self::assertSameLength($a, $b);

        $dot = 0.0;
        $squaredLengthA = 0.0;
        $squaredLengthB = 0.0;
        foreach ($a as $i => $valueA) {
            $dot += $valueA * $b[$i];
            $squaredLengthA += $valueA ** 2;
            $squaredLengthB += $b[$i] ** 2;
        }

        if ($squaredLengthA == 0.0 || $squaredLengthB == 0.0) {
            return 0.0;
        }
        $cosine = $dot / sqrt($squaredLengthA * $squaredLengthB);
        return max(-1.0, min(1.0, $cosine)); // 丸め誤差で範囲をはみ出さないようにする
    }

    /**
     * @param list<float> $a
     * @param list<float> $b
     */
    public static function euclideanDistance(array $a, array $b): float
    {
        self::assertSameLength($a, $b);

        $sum = 0.0;
        foreach ($a as $i => $valueA) {
            $sum += ($valueA - $b[$i]) ** 2;
        }
        return sqrt($sum);
    }

    /** -1〜1 の値を 0〜1 に移す。 */
    public static function toUnitInterval(float $value): float
    {
        return ($value + 1.0) / 2.0;
    }

    /**
     * @param list<float> $a
     * @param list<float> $b
     */
    private static function assertSameLength(array $a, array $b): void
    {
        if (count($a) !== count($b)) {
            throw new InvalidArgumentException(sprintf('長さが違います（%d と %d）。', count($a), count($b)));
        }
    }
}
