<?php

declare(strict_types=1);

namespace Suisen\Tests\Core\Search;

use PHPUnit\Framework\TestCase;
use Suisen\Core\Search\TextTokenizer;

final class TextTokenizerTest extends TestCase
{
    public function testJapaneseIsSplitIntoBigrams(): void
    {
        self::assertSame(['回路', '路設', '設計'], TextTokenizer::tokenize('回路設計'));
    }

    public function testAsciiWordsAreKeptWholeAndLowercased(): void
    {
        self::assertSame(['php', 'ros2'], TextTokenizer::tokenize('PHP、ROS2'));
    }

    public function testFullWidthAlphanumericsAreNormalized(): void
    {
        self::assertSame(['cad'], TextTokenizer::tokenize('ＣＡＤ'));
    }

    public function testPunctuationSplitsRuns(): void
    {
        self::assertSame(['化学', '実験', '高分', '分子'], TextTokenizer::tokenize('化学・実験（高分子）'));
    }
}
