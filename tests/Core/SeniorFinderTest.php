<?php

declare(strict_types=1);

namespace Suisen\Tests\Core;

use PHPUnit\Framework\TestCase;
use Suisen\Core\FeatureVector;
use Suisen\Core\Senior;
use Suisen\Core\SeniorFinder;

final class SeniorFinderTest extends TestCase
{
    private const KEYS = ['a', 'b'];

    public function testFindsClosestSeniorsInOrder(): void
    {
        $seniors = [
            self::senior(1, ['a' => 5, 'b' => 5]),
            self::senior(2, ['a' => 1, 'b' => 1]),
            self::senior(3, ['a' => 4, 'b' => 5]),
            self::senior(4, ['a' => 2, 'b' => 1]),
        ];
        $student = FeatureVector::fromNumbers(['a' => 5, 'b' => 5], self::KEYS);

        $found = (new SeniorFinder(self::KEYS, 2))->findSimilar($student, $seniors);

        self::assertSame([1, 3], array_map(static fn (Senior $senior): int => $senior->id, $found));
    }

    public function testSameDistanceIsOrderedById(): void
    {
        $seniors = [self::senior(9, ['a' => 4, 'b' => 4]), self::senior(2, ['a' => 4, 'b' => 4])];
        $student = FeatureVector::fromNumbers(['a' => 5, 'b' => 5], self::KEYS);

        $found = (new SeniorFinder(self::KEYS, 1))->findSimilar($student, $seniors);

        self::assertSame(2, $found[0]->id);
    }

    /** @param array<string, int> $values */
    private static function senior(int $id, array $values): Senior
    {
        return new Senior($id, 'テスト', FeatureVector::fromNumbers($values, self::KEYS), Senior::CAREER_EMPLOYMENT, 'テスト会社');
    }
}
