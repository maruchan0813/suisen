<?php

declare(strict_types=1);

namespace Suisen\Tests\Core\Search;

use PHPUnit\Framework\TestCase;
use Suisen\Core\Search\Chunk;
use Suisen\Core\Search\SyllabusChunker;

final class SyllabusChunkerTest extends TestCase
{
    public function testMakesSummaryAndPlanChunksWithHeading(): void
    {
        $chunks = (new SyllabusChunker(500))->chunk('34_0046', 'C言語', '情報／3年後期／必修', ['information'], 'C言語を学ぶ', ['ポインタ', '構造体']);

        self::assertSame(['34_0046#summary', '34_0046#plan1'], array_map(static fn (Chunk $chunk): string => $chunk->id, $chunks));
        self::assertStringStartsWith('情報／3年後期／必修／C言語', $chunks[0]->text);
        self::assertStringContainsString('授業計画：ポインタ、構造体', $chunks[1]->text);
    }

    public function testLongPlansAreSplitAtWeekBoundariesWithinLimit(): void
    {
        $topics = array_map(static fn (int $week): string => str_repeat('あ', 20) . $week, range(1, 15));
        $chunks = (new SyllabusChunker(120))->chunk('k', '科目', 'ラベル', ['x'], '', $topics);

        self::assertGreaterThan(2, count($chunks));
        foreach ($chunks as $chunk) {
            self::assertLessThanOrEqual(120, mb_strlen($chunk->text));
        }
        self::assertStringContainsString('あ15', end($chunks)->text); // 最後の週も落ちていない
    }

    public function testRepeatedTopicsAreIncludedOnce(): void
    {
        $chunks = (new SyllabusChunker(500))->chunk('k', '科目', 'ラベル', ['x'], '', ['実験', '実験', 'まとめ']);

        self::assertSame(1, substr_count($chunks[1]->text, '実験'));
    }
}
