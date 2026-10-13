<?php

declare(strict_types=1);

namespace Suisen\Core;

use InvalidArgumentException;

/**
 * 学生の特徴とコースの特徴を比べて、コースに順位をつける。
 *
 * 学生は1〜5、コースは単位の割合で物差しが違うため、どちらも「平均との差」で比べる。
 * - 興味：学生とコースの相関係数
 * - 学び方：学生は自分の平均との差、コースは5コースの平均との差。そのコサイン類似度
 *   （理論はどのコースも割合が近く、平均を引かないと差が埋もれるため）
 */
final class CourseRecommender
{
    /**
     * @param list<string> $interestKeys
     * @param list<string> $styleKeys
     */
    public function __construct(
        private readonly array $interestKeys,
        private readonly array $styleKeys,
        private readonly float $interestWeight,
        private readonly float $styleWeight,
    ) {
    }

    /** @param list<Course> $courses */
    public function rank(FeatureVector $student, array $courses): CourseRanking
    {
        if ($courses === []) {
            throw new InvalidArgumentException('比べるコースがありません。');
        }

        $styleAverages = $this->averageStyleOf($courses);
        $scores = array_map(
            fn (Course $course): CourseScore => $this->score($student, $course, $styleAverages),
            $courses,
        );
        usort($scores, [self::class, 'compareByRank']);

        return new CourseRanking($scores);
    }

    /** @param list<float> $styleAverages */
    private function score(FeatureVector $student, Course $course, array $styleAverages): CourseScore
    {
        $interestScore = $this->interestScore($student, $course);
        $styleScore = $this->styleScore($student, $course, $styleAverages);

        return new CourseScore(
            course: $course,
            total: $this->interestWeight * $interestScore + $this->styleWeight * $styleScore,
            interestScore: $interestScore,
            styleScore: $styleScore,
            interestContributions: $this->interestContributions($student, $course),
        );
    }

    private function interestScore(FeatureVector $student, Course $course): float
    {
        $correlation = Similarity::pearson(
            $student->pick($this->interestKeys),
            $course->features->pick($this->interestKeys),
        );
        return Similarity::toUnitInterval($correlation);
    }

    /** @param list<float> $styleAverages */
    private function styleScore(FeatureVector $student, Course $course, array $styleAverages): float
    {
        $studentPreference = Similarity::centered($student->pick($this->styleKeys));
        $courseCharacter = self::subtract($course->features->pick($this->styleKeys), $styleAverages);
        return Similarity::toUnitInterval(Similarity::cosine($studentPreference, $courseCharacter));
    }

    /**
     * 興味の項目ごとの「学生とコースの両方で高い度合い」。
     * 学生もコースも平均より高い項目だけ「学生の平均との差 × コースの平均との差」とし、それ以外は0。
     * （両方とも平均より低い項目も掛け算では正になるが、それは「共通の興味」ではないため）
     *
     * @return array<string, float>
     */
    private function interestContributions(FeatureVector $student, Course $course): array
    {
        $studentDeviation = Similarity::centered($student->pick($this->interestKeys));
        $courseDeviation = Similarity::centered($course->features->pick($this->interestKeys));

        $contributions = [];
        foreach ($this->interestKeys as $i => $key) {
            $bothHigh = $studentDeviation[$i] > 0.0 && $courseDeviation[$i] > 0.0;
            $contributions[$key] = $bothHigh ? $studentDeviation[$i] * $courseDeviation[$i] : 0.0;
        }
        return $contributions;
    }

    /**
     * @param list<Course> $courses
     * @return list<float> 学び方の項目ごとの、全コースの平均
     */
    private function averageStyleOf(array $courses): array
    {
        $averages = [];
        foreach ($this->styleKeys as $key) {
            $averages[] = Similarity::mean(
                array_map(static fn (Course $course): float => $course->features->get($key), $courses),
            );
        }
        return $averages;
    }

    /**
     * @param list<float> $values
     * @param list<float> $subtrahends
     * @return list<float>
     */
    private static function subtract(array $values, array $subtrahends): array
    {
        return array_map(static fn (float $value, float $subtrahend): float => $value - $subtrahend, $values, $subtrahends);
    }

    /** 点数 → 興味の点数 → 表示順 で並べる。 */
    private static function compareByRank(CourseScore $a, CourseScore $b): int
    {
        return [round($b->total, 10), round($b->interestScore, 10), $a->course->displayOrder]
            <=> [round($a->total, 10), round($a->interestScore, 10), $b->course->displayOrder];
    }
}
