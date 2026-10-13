<?php

declare(strict_types=1);

namespace Suisen\Tests\Core;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Suisen\Core\Similarity;

final class SimilarityTest extends TestCase
{
    public function testPearsonIsOneForSameDirection(): void
    {
        self::assertEqualsWithDelta(1.0, Similarity::pearson([1.0, 2.0, 3.0], [10.0, 20.0, 30.0]), 1e-9);
    }

    public function testPearsonIsMinusOneForOppositeDirection(): void
    {
        self::assertEqualsWithDelta(-1.0, Similarity::pearson([1.0, 2.0, 3.0], [3.0, 2.0, 1.0]), 1e-9);
    }

    public function testPearsonIsZeroWhenOneSideHasNoVariance(): void
    {
        self::assertSame(0.0, Similarity::pearson([3.0, 3.0, 3.0], [1.0, 2.0, 3.0]));
    }

    public function testCosineIsZeroForZeroVector(): void
    {
        self::assertSame(0.0, Similarity::cosine([0.0, 0.0], [1.0, 2.0]));
    }

    public function testEuclideanDistance(): void
    {
        self::assertEqualsWithDelta(5.0, Similarity::euclideanDistance([0.0, 0.0], [3.0, 4.0]), 1e-9);
    }

    public function testCenteredSubtractsMean(): void
    {
        self::assertSame([-1.0, 0.0, 1.0], Similarity::centered([1.0, 2.0, 3.0]));
    }

    public function testToUnitInterval(): void
    {
        self::assertSame(0.0, Similarity::toUnitInterval(-1.0));
        self::assertSame(0.5, Similarity::toUnitInterval(0.0));
        self::assertSame(1.0, Similarity::toUnitInterval(1.0));
    }

    public function testDifferentLengthsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Similarity::cosine([1.0], [1.0, 2.0]);
    }
}
