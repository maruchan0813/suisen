<?php

declare(strict_types=1);

namespace Suisen\Tests;

use PHPUnit\Framework\TestCase;
use Suisen\Service\AskedQuestions;

final class AskedQuestionsTest extends TestCase
{
    public function testCollectsQuestionsOnlyFromAssistant(): void
    {
        $history = [
            ['role' => 'assistant', 'content' => "こんにちは！\n楽しかった授業は何ですか？"],
            ['role' => 'user', 'content' => 'どんな授業がありますか？'],
            ['role' => 'assistant', 'content' => '「データベース基礎」があります。手を動かすのは好きですか？それとも理論が好きですか？'],
            ['role' => 'assistant', 'content' => '手を動かすのは好きですか？'],
        ];

        self::assertSame(
            ['楽しかった授業は何ですか？', '手を動かすのは好きですか？', 'それとも理論が好きですか？'],
            AskedQuestions::from($history),
        );
    }
}
