<?php

declare(strict_types=1);

namespace Suisen\Core;

/**
 * 点数の高い順に並んだコース。
 */
final class CourseRanking
{
    /** @param list<CourseScore> $scores 点数の高い順 */
    public function __construct(private readonly array $scores)
    {
    }

    public function best(): CourseScore
    {
        return $this->scores[0];
    }

    /** @return list<CourseScore> */
    public function all(): array
    {
        return $this->scores;
    }

    /** @return array<string, float> コースの code => 点数 */
    public function totalsByCourseCode(): array
    {
        $totals = [];
        foreach ($this->scores as $score) {
            $totals[$score->course->code] = round($score->total, 4);
        }
        return $totals;
    }
}
