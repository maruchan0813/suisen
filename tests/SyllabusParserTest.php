<?php

declare(strict_types=1);

namespace Suisen\Tests;

use PHPUnit\Framework\TestCase;
use Suisen\Infra\SyllabusParser;

final class SyllabusParserTest extends TestCase
{
    public function testParsesRealSyllabusPage(): void
    {
        $html = (string) file_get_contents(dirname(__DIR__) . '/data/syllabus_raw/detail/34_0046.html');

        $parsed = SyllabusParser::parse($html);

        self::assertStringStartsWith('プログラミング基礎で習得した考え方を元に', $parsed['summary']);
        self::assertSame('C言語の特徴，Cプログラムの実行方法，文法', $parsed['weekly_topics'][0]);
        self::assertSame('ポインタと配列', $parsed['weekly_topics'][1]);
    }

    public function testEmptyWeeksAreSkipped(): void
    {
        $html = '<p>授業計画</p><p>1週</p><p>ガイダンス</p><p>2週</p><p>2ndQ</p><p>3週</p><p>後期</p><p>4週</p><p>まとめ</p><p>5週</p><p>モデルコアカリキュラムの学習内容と到達目標</p>';

        self::assertSame(['ガイダンス', 'まとめ'], SyllabusParser::parse($html)['weekly_topics']);
    }

    public function testReturnsEmptyWhenMarkersAreMissing(): void
    {
        self::assertSame(['summary' => '', 'weekly_topics' => []], SyllabusParser::parse('<p>なにもない</p>'));
    }
}
