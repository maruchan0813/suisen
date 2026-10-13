<?php

declare(strict_types=1);

namespace Suisen\Tests\Core;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Suisen\Core\PromptTemplate;

final class PromptTemplateTest extends TestCase
{
    public function testReplacesPlaceholders(): void
    {
        self::assertSame('残り 3 回', PromptTemplate::render('残り {{remaining}} 回', ['remaining' => 3]));
    }

    public function testMissingValueIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PromptTemplate::render('{{name}}', []);
    }
}
