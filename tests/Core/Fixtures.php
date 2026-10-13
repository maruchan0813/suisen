<?php

declare(strict_types=1);

namespace Suisen\Tests\Core;

use Suisen\Core\Course;
use Suisen\Core\CourseRecommender;
use Suisen\Core\FeatureVector;
use Suisen\Infra\Csv;
use Suisen\Policy;

/** テストで使う、本物の方針とコースのデータ。 */
final class Fixtures
{
    public static function policy(): Policy
    {
        return Policy::load(dirname(__DIR__, 2) . '/policy');
    }

    /** @return list<Course> */
    public static function courses(): array
    {
        $keys = self::policy()->featureKeys();
        return array_map(static fn (array $row): Course => new Course(
            (int) $row['id'],
            $row['code'],
            $row['name'],
            FeatureVector::fromNumbers($row, $keys),
            (int) $row['display_order'],
        ), Csv::read(dirname(__DIR__, 2) . '/data/courses.csv'));
    }

    public static function recommender(): CourseRecommender
    {
        $policy = self::policy();
        return new CourseRecommender(
            $policy->featureKeys(Policy::GROUP_INTEREST),
            $policy->featureKeys(Policy::GROUP_STYLE),
            $policy->float('INTEREST_WEIGHT'),
            $policy->float('STYLE_WEIGHT'),
        );
    }

    /**
     * 指定しなかった項目は $default にした学生。
     *
     * @param array<string, int> $values
     */
    public static function student(array $values, int $default = 3): FeatureVector
    {
        $keys = self::policy()->featureKeys();
        return FeatureVector::fromNumbers($values + array_fill_keys($keys, $default), $keys);
    }
}
