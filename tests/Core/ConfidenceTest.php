<?php

declare(strict_types=1);

namespace Suisen\Tests\Core;

use PHPUnit\Framework\TestCase;
use Suisen\Core\Confidence;

final class ConfidenceTest extends TestCase
{
    private function confidence(): Confidence
    {
        return new Confidence(midRatio: 0.4, highRatio: 0.7, marginFull: 0.1, evidenceWeight: 0.5);
    }

    public function testEvidenceLevelBoundariesWith13Topics(): void
    {
        $c = $this->confidence();

        self::assertSame(Confidence::LOW, $c->evidenceLevel(0, 13));
        self::assertSame(Confidence::LOW, $c->evidenceLevel(5, 13));
        self::assertSame(Confidence::MEDIUM, $c->evidenceLevel(6, 13));
        self::assertSame(Confidence::MEDIUM, $c->evidenceLevel(9, 13));
        self::assertSame(Confidence::HIGH, $c->evidenceLevel(10, 13));
        self::assertSame(Confidence::HIGH, $c->evidenceLevel(13, 13));
    }

    public function testNoTopicsAtAllIsLow(): void
    {
        self::assertSame(Confidence::LOW, $this->confidence()->evidenceLevel(0, 0));
    }

    public function testResultIsHighWhenEverythingWasAskedAndTheWinnerIsClear(): void
    {
        self::assertSame(Confidence::HIGH, $this->confidence()->resultLevel(13, 13, 0.80, 0.65, false));
    }

    public function testResultDropsWhenTheTopTwoAreTied(): void
    {
        // A = 1, B = 0 → 0.5
        self::assertSame(Confidence::MEDIUM, $this->confidence()->resultLevel(13, 13, 0.70, 0.70, false));
    }

    public function testMarginIsCappedAtTheFullMargin(): void
    {
        // 差が基準を超えても、B は 1 より大きくならない
        self::assertSame(
            $this->confidence()->resultLevel(13, 13, 0.9, 0.5, false),
            $this->confidence()->resultLevel(13, 13, 0.9, 0.8, false),
        );
    }

    public function testFewTopicsKeepTheResultBelowHigh(): void
    {
        // A = 5/13、B = 1 → 約 0.69
        self::assertSame(Confidence::MEDIUM, $this->confidence()->resultLevel(5, 13, 0.8, 0.6, false));
    }

    public function testAllSameAnswersAreAlwaysLow(): void
    {
        self::assertSame(Confidence::LOW, $this->confidence()->resultLevel(13, 13, 0.8, 0.6, true));
    }

    public function testEvidenceWeightIsConfigurable(): void
    {
        $onlyMargin = new Confidence(0.4, 0.7, 0.1, 0.0);

        self::assertSame(Confidence::HIGH, $onlyMargin->resultLevel(0, 13, 0.8, 0.6, false));
    }

    public function testMarginIsClearOnlyWhenItReachesTheFullMargin(): void
    {
        $c = $this->confidence();

        self::assertTrue($c->marginIsClear(0.80, 0.70));
        self::assertFalse($c->marginIsClear(0.80, 0.75));
    }
}
