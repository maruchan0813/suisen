<?php

declare(strict_types=1);

namespace Suisen\Tests\Core;

use PHPUnit\Framework\TestCase;
use Suisen\Core\NextTopicPicker;

final class NextTopicPickerTest extends TestCase
{
    private const LABELS = ['力学・材料', '熱・流体', 'プログラミング・情報', '手を動かす'];

    public function testPicksFirstTopicNotMentionedYet(): void
    {
        $picker = new NextTopicPicker(self::LABELS);

        self::assertSame('熱・流体', $picker->pick(['材料の強さに興味はありますか？', 'あまりないです']));
    }

    public function testStudentMentionsAlsoCount(): void
    {
        $picker = new NextTopicPicker(self::LABELS);

        self::assertSame('手を動かす', $picker->pick(['力学は好き', '熱の話', 'プログラミングが好きです']));
    }

    public function testSkipsTopicsAlreadyAsked(): void
    {
        $picker = new NextTopicPicker(self::LABELS);

        // AI が「力学・材料」を「物の動きや形」と言い換えても、聞かせた記録があれば選ばない
        self::assertSame('熱・流体', $picker->pick(['物の動きや形について考えるのは得意ですか？'], ['力学・材料']));
    }

    public function testReturnsNullWhenEverythingIsMentioned(): void
    {
        $picker = new NextTopicPicker(self::LABELS);

        self::assertNull($picker->pick(['力学', '流体', '情報', '手を動かす']));
    }

    public function testCoveredTopicsIncludeAskedAndStudentMentioned(): void
    {
        $picker = new NextTopicPicker(['化学', '生物・食品', 'プログラミング・情報']);

        // 化学は聞いた。プログラミングは学生が自分から話した。生物・食品は話に出ていない
        self::assertSame(
            ['化学', 'プログラミング・情報'],
            $picker->coveredTopics(['プログラミングが好きです'], ['化学']),
        );
    }

    public function testCoveredTopicsAreEmptyAtTheStart(): void
    {
        self::assertSame([], (new NextTopicPicker(['化学']))->coveredTopics([], []));
    }
}
