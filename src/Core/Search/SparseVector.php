<?php

declare(strict_types=1);

namespace Suisen\Core\Search;

/**
 * 0 でない値だけを「語 => 値」で持つベクトルの計算。
 */
final class SparseVector
{
    /**
     * @param array<string, float> $vector
     * @return array<string, float> 長さを1にしたもの（長さ0ならそのまま）
     */
    public static function normalize(array $vector): array
    {
        $length = sqrt(array_sum(array_map(static fn (float $value): float => $value ** 2, $vector)));
        if ($length == 0.0) {
            return $vector;
        }
        return array_map(static fn (float $value): float => $value / $length, $vector);
    }

    /**
     * 内積。長さ1のベクトルどうしなら、コサイン類似度と同じ。
     *
     * @param array<string, float> $a
     * @param array<string, float> $b
     */
    public static function dot(array $a, array $b): float
    {
        if (count($a) > count($b)) {
            [$a, $b] = [$b, $a]; // 短い方で回す
        }
        $sum = 0.0;
        foreach ($a as $term => $value) {
            if (isset($b[$term])) {
                $sum += $value * $b[$term];
            }
        }
        return $sum;
    }
}
