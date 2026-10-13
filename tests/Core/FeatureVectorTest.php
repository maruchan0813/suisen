<?php

declare(strict_types=1);

namespace Suisen\Tests\Core;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Suisen\Core\FeatureVector;

final class FeatureVectorTest extends TestCase
{
    private const KEYS = ['a', 'b', 'c', 'd', 'e'];

    public function testSanitizeKeepsValidValuesAndReplacesInvalidOnes(): void
    {
        $vector = FeatureVector::sanitize(
            ['a' => 5, 'b' => '2', 'c' => 9, 'd' => 'x'],   // e は足りない
            self::KEYS,
            1,
            5,
            3,
        );

        self::assertSame(['a' => 5.0, 'b' => 2.0, 'c' => 3.0, 'd' => 3.0, 'e' => 3.0], $vector->toArray());
    }

    public function testFromNumbersRejectsMissingKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FeatureVector::fromNumbers(['a' => 1], ['a', 'b']);
    }

    public function testPickReturnsValuesInRequestedOrder(): void
    {
        $vector = FeatureVector::fromNumbers(['a' => 1, 'b' => 2, 'c' => 3], ['a', 'b', 'c']);
        self::assertSame([3.0, 1.0], $vector->pick(['c', 'a']));
    }
}
