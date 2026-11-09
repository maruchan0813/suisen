<?php

declare(strict_types=1);

namespace Suisen\Tests\Core;

use PHPUnit\Framework\TestCase;
use Suisen\Core\FollowUpRule;

final class FollowUpRuleTest extends TestCase
{
    private const LONG_ANSWER = 'ロボットが好きで、よく動画を見ています';

    private function rule(): FollowUpRule
    {
        return new FollowUpRule(1, 10, ['特にない', 'わからない']);
    }

    public function testDeepensTheTopicJustAsked(): void
    {
        self::assertSame('制御・ロボット', $this->rule()->topicToDeepen([null, '制御・ロボット'], self::LONG_ANSWER));
    }

    public function testDoesNotDeepenTwice(): void
    {
        // 最初に聞いた + 深掘り1回 = 同じ項目が2回続いたので、次の項目へ移る
        self::assertNull($this->rule()->topicToDeepen([null, '制御・ロボット', '制御・ロボット'], self::LONG_ANSWER));
    }

    public function testAllowsMoreFollowUpsWhenConfigured(): void
    {
        $rule = new FollowUpRule(2, 10, []);

        self::assertSame('化学', $rule->topicToDeepen(['化学', '化学'], self::LONG_ANSWER));
        self::assertNull($rule->topicToDeepen(['化学', '化学', '化学'], self::LONG_ANSWER));
    }

    public function testDoesNotDeepenWhenLastReplyHadNoTopic(): void
    {
        // あいさつや、質問に答えただけの返事のあと
        self::assertNull($this->rule()->topicToDeepen([null], self::LONG_ANSWER));
        self::assertNull($this->rule()->topicToDeepen(['化学', null], self::LONG_ANSWER));
        self::assertNull($this->rule()->topicToDeepen([], self::LONG_ANSWER));
    }

    public function testDoesNotDeepenShortAnswer(): void
    {
        self::assertNull($this->rule()->topicToDeepen(['化学'], 'まあまあ好き'));
    }

    public function testDoesNotDeepenWhenStudentHasNothingToSay(): void
    {
        self::assertNull($this->rule()->topicToDeepen(['化学'], '化学は特にないので、ほかの話がしたいです'));
    }

    public function testCountsOnlyTheTrailingRunOfTheSameTopic(): void
    {
        // 前に別の回で同じ項目を聞いていても、末尾から続いていなければ数えない
        self::assertSame('化学', $this->rule()->topicToDeepen(['化学', '熱・流体', '化学'], self::LONG_ANSWER));
    }

    public function testDeepensEveryOtherTopicWhenConfigured(): void
    {
        $rule = new FollowUpRule(1, 10, [], 2);

        // 1つ目の項目は深掘りする
        self::assertSame('化学', $rule->topicToDeepen(['化学'], self::LONG_ANSWER));
        // 2つ目の項目は深掘りせず、次の項目へ移る
        self::assertNull($rule->topicToDeepen(['化学', '化学', '熱・流体'], self::LONG_ANSWER));
        // 3つ目の項目は深掘りする
        self::assertSame('生物・食品', $rule->topicToDeepen(['化学', '化学', '熱・流体', '生物・食品'], self::LONG_ANSWER));
    }

    public function testDeepensEveryTopicByDefault(): void
    {
        self::assertSame('熱・流体', $this->rule()->topicToDeepen(['化学', '化学', '熱・流体'], self::LONG_ANSWER));
    }
}
