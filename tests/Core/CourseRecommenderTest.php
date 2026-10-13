<?php

declare(strict_types=1);

namespace Suisen\Tests\Core;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CourseRecommenderTest extends TestCase
{
    /** @return iterable<string, array{array<string, int>, int, string}> */
    public static function typicalStudents(): iterable
    {
        yield '化学と生物が好き' => [['chemistry' => 5, 'biology_food' => 4, 'experiment' => 5], 2, 'chemistry'];
        yield 'プログラミングが好き' => [['programming' => 5, 'circuits_electronics' => 4, 'coding' => 5], 2, 'information'];
        yield '手を動かすものづくりが好き' => [['design_making' => 5, 'mechanics' => 4, 'thermo_fluid' => 4, 'hands_on' => 5], 2, 'mechanical'];
        yield 'ロボットとプログラミングが好き' => [['control_robotics' => 5, 'programming' => 4, 'design_making' => 4, 'coding' => 4], 2, 'robotics'];
        yield '電気と回路が好き' => [['power_machines' => 5, 'circuits_electronics' => 5, 'experiment' => 4, 'theory' => 4], 2, 'electrical'];
        // vault の教訓：全体に高めに答える人でも、一番高い分野のコースが選ばれる
        yield '化学だけ5で他は全部4' => [['chemistry' => 5], 4, 'chemistry'];
    }

    /** @param array<string, int> $values */
    #[DataProvider('typicalStudents')]
    public function testTypicalStudentGetsExpectedCourse(array $values, int $default, string $expectedCode): void
    {
        $ranking = Fixtures::recommender()->rank(Fixtures::student($values, $default), Fixtures::courses());

        self::assertSame($expectedCode, $ranking->best()->course->code);
    }

    public function testNeutralStudentFallsBackToDisplayOrder(): void
    {
        $ranking = Fixtures::recommender()->rank(Fixtures::student([]), Fixtures::courses());

        self::assertSame('mechanical', $ranking->best()->course->code);
        self::assertSame([], $ranking->best()->reasonKeys(2));
    }

    public function testReasonKeysAreInterestsSharedByStudentAndCourse(): void
    {
        $ranking = Fixtures::recommender()->rank(
            Fixtures::student(['chemistry' => 5, 'biology_food' => 5], 2),
            Fixtures::courses(),
        );

        self::assertSame(['chemistry', 'biology_food'], $ranking->best()->reasonKeys(2));
    }

    /** 学生もコースも平均より低い項目（関心がない・学ばない）は、推薦の理由にしない。 */
    public function testReasonKeysIgnoreInterestsLowOnBothSides(): void
    {
        $ranking = Fixtures::recommender()->rank(
            Fixtures::student(['design_making' => 5, 'control_robotics' => 5, 'hands_on' => 5]),
            Fixtures::courses(),
        );

        self::assertNotContains('power_machines', $ranking->best()->reasonKeys(2));
        self::assertSame(['design_making'], array_slice($ranking->best()->reasonKeys(2), 0, 1));
    }

    public function testRankingContainsEveryCourseInDescendingOrder(): void
    {
        $scores = Fixtures::recommender()->rank(Fixtures::student(['programming' => 5]), Fixtures::courses())->all();

        self::assertCount(5, $scores);
        for ($i = 1; $i < count($scores); $i++) {
            self::assertGreaterThanOrEqual($scores[$i]->total, $scores[$i - 1]->total);
        }
    }

    /** vault の教訓：でたらめな回答でも、第1希望が特定のコースに偏りすぎないか。 */
    public function testRandomStudentsAreSpreadAcrossCourses(): void
    {
        mt_srand(1);
        $keys = Fixtures::policy()->featureKeys();
        $counts = [];
        $total = 1000;
        for ($i = 0; $i < $total; $i++) {
            $values = array_combine($keys, array_map(static fn (): int => mt_rand(1, 5), $keys));
            $code = Fixtures::recommender()->rank(Fixtures::student($values), Fixtures::courses())->best()->course->code;
            $counts[$code] = ($counts[$code] ?? 0) + 1;
        }

        self::assertCount(5, $counts);
        foreach ($counts as $code => $count) {
            self::assertGreaterThan($total * 0.10, $count, "{$code} が少なすぎます");
            self::assertLessThan($total * 0.30, $count, "{$code} が多すぎます");
        }
    }
}
