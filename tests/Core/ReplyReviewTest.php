<?php

declare(strict_types=1);

namespace Suisen\Tests\Core;

use PHPUnit\Framework\TestCase;
use Suisen\Core\ReplyReview;

final class ReplyReviewTest extends TestCase
{
    private ReplyReview $review;

    protected function setUp(): void
    {
        $this->review = new ReplyReview(['微生物工学Ⅰ', '微生物工学Ⅱ', 'ＣＡＤ演習', '環境化学工学', 'メカトロニクス概論（前期）'], 0.45);
    }

    public function testFindsSubjectsThatAreNotInSyllabus(): void
    {
        $reply = '「環境科学」や「環境化学工学」で学びます。「微生物工学Ⅰ・Ⅱ」「CAD演習」「メカトロニクス概論」もあります。';

        self::assertSame(['環境科学'], $this->review->unknownSubjects($reply, '環境に関する授業はありますか？'));
    }

    public function testQuotedWordsFromStudentAreNotFlagged(): void
    {
        $reply = '「量子ロボット工学」という授業は分かりません。';

        self::assertSame([], $this->review->unknownSubjects($reply, '存在しない授業「量子ロボット工学」について教えて'));
    }

    public function testNonSubjectQuotesAreIgnored(): void
    {
        self::assertSame([], $this->review->unknownSubjects('「手を動かす」のが好きですか？', ''));
    }

    public function testFindsRepeatedQuestions(): void
    {
        $asked = ['工作実習などで金属を削ったり溶接したりして、部品を加工するのが好きですか？', 'アニメは好きですか？'];
        $reply = 'いいですね。ところで、工作実習などで金属を削ったり溶接したりして、部品を加工するのが好きですか？';

        self::assertSame([$asked[0]], $this->review->repeatedQuestions($reply, $asked));
    }

    public function testRemovesRepeatedQuestionSentences(): void
    {
        $asked = ['熱や流体について、気になることはありますか？'];
        $reply = "「量子ロボット工学」は分かりません。\n\nさて、熱や流体について、気になることはありますか？";

        self::assertSame('「量子ロボット工学」は分かりません。', $this->review->withoutRepeatedQuestions($reply, $asked));
    }

    public function testKeepsReplyWhenEverythingWouldBeRemoved(): void
    {
        $asked = ['アニメは好きですか？'];

        self::assertSame('アニメは好きですか？', $this->review->withoutRepeatedQuestions('アニメは好きですか？', $asked));
    }

    public function testRemovesSentencesWithUnknownSubjects(): void
    {
        $reply = 'はい、あります。「環境科学」で大気を学びます。「環境化学工学」ではエネルギーと環境を学びます。';

        self::assertSame(
            'はい、あります。「環境化学工学」ではエネルギーと環境を学びます。',
            $this->review->withoutSentencesMentioning($reply, ['環境科学']),
        );
    }

    public function testFindsForbiddenPhrases(): void
    {
        $review = new ReplyReview([], 0.45, ['参考資料', '授業の一覧']);

        self::assertSame(['参考資料'], $review->forbiddenPhrasesIn('参考資料にある実験は次のとおりです。'));
    }

    public function testDifferentQuestionsAreNotRepeated(): void
    {
        $asked = ['工作実習で金属を削るのは好きですか？'];

        self::assertSame([], $this->review->repeatedQuestions('熱いものや冷たいものの仕組みに興味はありますか？', $asked));
    }
}
