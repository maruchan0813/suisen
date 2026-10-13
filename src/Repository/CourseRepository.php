<?php

declare(strict_types=1);

namespace Suisen\Repository;

use Suisen\Core\Course;
use Suisen\Core\FeatureVector;
use Suisen\Infra\Database;

final class CourseRepository
{
    /** @param list<string> $featureKeys */
    public function __construct(
        private readonly Database $db,
        private readonly array $featureKeys,
    ) {
    }

    /** @return list<Course> 表示順 */
    public function all(): array
    {
        $rows = $this->db->pdo()
            ->query('SELECT id, code, name, features, display_order FROM courses ORDER BY display_order')
            ->fetchAll();

        return array_map(fn (array $row): Course => new Course(
            id: (int) $row['id'],
            code: $row['code'],
            name: $row['name'],
            features: FeatureVector::fromNumbers(json_decode($row['features'], true), $this->featureKeys),
            displayOrder: (int) $row['display_order'],
        ), $rows);
    }
}
