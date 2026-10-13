<?php

declare(strict_types=1);

namespace Suisen\Tests;

use PHPUnit\Framework\TestCase;
use Suisen\Infra\Csv;
use Suisen\Tests\Core\Fixtures;

/** 方針のファイルとデータのファイルが食い違っていないかを確かめる。 */
final class PolicyTest extends TestCase
{
    public function testDataFilesHaveEveryFeatureColumn(): void
    {
        $keys = Fixtures::policy()->featureKeys();
        foreach (['courses.csv', 'seniors_test.csv'] as $file) {
            $header = array_keys(Csv::read(dirname(__DIR__) . "/data/{$file}")[0]);
            self::assertSame([], array_diff($keys, $header), "{$file} に足りない列があります");
        }
    }

    public function testEveryCourseHasSummary(): void
    {
        foreach (Fixtures::courses() as $course) {
            self::assertNotSame('', Fixtures::policy()->courseSummary($course->code), $course->code);
        }
    }

    public function testPromptsCanBeRead(): void
    {
        foreach (['chat_system', 'extract_features', 'career_story'] as $name) {
            self::assertNotSame('', Fixtures::policy()->prompt($name));
        }
    }
}
