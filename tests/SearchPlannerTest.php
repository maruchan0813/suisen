<?php

declare(strict_types=1);

namespace Suisen\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Suisen\Service\SearchPlanner;

final class SearchPlannerTest extends TestCase
{
    /** @return iterable<array{string, bool}> */
    public static function messages(): iterable
    {
        yield ['ROSは使いますか？', true];
        yield ['CADは何年生で習うの', true];
        yield ['違いは何ですか?', true];
        yield ['3年次の情報コースの授業にどのようなものがあるか教えて', true];
        yield ['ROSについて知りたいです', true];
        yield ['ロボットを作ってみたいです', false];
        yield ['手を動かして何かを作るのが好きです。', false];
    }

    #[DataProvider('messages')]
    public function testLooksLikeQuestion(string $message, bool $expected): void
    {
        self::assertSame($expected, SearchPlanner::looksLikeQuestion($message));
    }
}
