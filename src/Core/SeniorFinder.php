<?php

declare(strict_types=1);

namespace Suisen\Core;

/**
 * 学生に特徴が似ている先輩を選ぶ。
 * 学生も先輩も同じ物差し（1〜5）なので、値の高さも含めて比べられるユークリッド距離を使う。
 */
final class SeniorFinder
{
    /** @param list<string> $featureKeys */
    public function __construct(
        private readonly array $featureKeys,
        private readonly int $count,
    ) {
    }

    /**
     * 距離が小さい順に選ぶ。同じ距離なら id の小さい順。
     *
     * @param list<Senior> $seniors
     * @return list<Senior>
     */
    public function findSimilar(FeatureVector $student, array $seniors): array
    {
        $studentValues = $student->pick($this->featureKeys);
        $ranked = array_map(
            fn (Senior $senior): array => [
                'distance' => Similarity::euclideanDistance($studentValues, $senior->features->pick($this->featureKeys)),
                'senior'   => $senior,
            ],
            $seniors,
        );
        usort(
            $ranked,
            static fn (array $a, array $b): int => [$a['distance'], $a['senior']->id] <=> [$b['distance'], $b['senior']->id],
        );

        return array_map(
            static fn (array $item): Senior => $item['senior'],
            array_slice($ranked, 0, $this->count),
        );
    }
}
