<?php

declare(strict_types=1);

namespace Suisen\Repository;

use Suisen\Core\FeatureVector;
use Suisen\Core\Senior;
use Suisen\Infra\Database;

final class SeniorRepository
{
    /** @param list<string> $featureKeys */
    public function __construct(
        private readonly Database $db,
        private readonly array $featureKeys,
    ) {
    }

    /**
     * @param list<int> $ids
     * @return list<Senior> $ids の順
     */
    public function findByIds(array $ids): array
    {
        $byId = [];
        foreach ($this->all() as $senior) {
            $byId[$senior->id] = $senior;
        }
        return array_values(array_filter(array_map(static fn (int $id): ?Senior => $byId[$id] ?? null, $ids)));
    }

    /** @return list<Senior> */
    public function all(): array
    {
        $rows = $this->db->pdo()->query(
            'SELECT s.id, c.name AS course_name, s.features, s.career_type, s.destination
             FROM seniors s JOIN courses c ON c.id = s.course_id ORDER BY s.id',
        )->fetchAll();

        return array_map(fn (array $row): Senior => new Senior(
            id: (int) $row['id'],
            courseName: $row['course_name'],
            features: FeatureVector::fromNumbers(json_decode($row['features'], true), $this->featureKeys),
            careerType: $row['career_type'],
            destination: $row['destination'],
        ), $rows);
    }
}
