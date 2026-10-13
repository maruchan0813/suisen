<?php

declare(strict_types=1);

namespace Suisen\Repository;

use Suisen\Infra\Database;

final class DiagnosisRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param array<string, float> $features
     * @param array<string, float> $courseScores
     * @param list<int> $seniorIds
     * @param list<string> $sources 参考にしたシラバスの見出し
     */
    public function save(
        int $conversationId,
        int $userId,
        array $features,
        int $courseId,
        array $courseScores,
        array $seniorIds,
        string $recommendationText,
        string $careerText,
        array $sources,
    ): void {
        $this->db->pdo()->prepare(
            'INSERT INTO diagnoses
               (conversation_id, user_id, features, course_id, course_scores, senior_ids, recommendation_text, career_text, sources)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        )->execute([
            $conversationId,
            $userId,
            self::toJson($features),
            $courseId,
            self::toJson($courseScores),
            self::toJson($seniorIds),
            $recommendationText,
            $careerText,
            self::toJson($sources),
        ]);
    }

    /**
     * その利用者の、その会話の診断結果（他人の診断は null）。
     *
     * @return array{course_code: string, course_name: string, features: array<string, float>, senior_ids: list<int>,
     *               recommendation_text: ?string, career_text: string, sources: list<string>, created_at: string}|null
     */
    public function findFor(int $conversationId, int $userId): ?array
    {
        $statement = $this->db->pdo()->prepare(
            'SELECT c.code AS course_code, c.name AS course_name, d.features, d.senior_ids,
                    d.recommendation_text, d.career_text, d.sources, d.created_at
             FROM diagnoses d JOIN courses c ON c.id = d.course_id
             WHERE d.conversation_id = ? AND d.user_id = ?',
        );
        $statement->execute([$conversationId, $userId]);
        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }
        $row['features'] = json_decode($row['features'], true);
        $row['senior_ids'] = json_decode($row['senior_ids'], true);
        $row['sources'] = $row['sources'] === null ? [] : json_decode($row['sources'], true);
        return $row;
    }

    public function latestConversationIdFor(int $userId): ?int
    {
        $statement = $this->db->pdo()->prepare('SELECT conversation_id FROM diagnoses WHERE user_id = ? ORDER BY id DESC LIMIT 1');
        $statement->execute([$userId]);
        $id = $statement->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /** @return array{course_name: string, created_at: string}|null */
    public function latestFor(int $userId): ?array
    {
        $statement = $this->db->pdo()->prepare(
            'SELECT c.name AS course_name, d.created_at FROM diagnoses d
             JOIN courses c ON c.id = d.course_id
             WHERE d.user_id = ? ORDER BY d.id DESC LIMIT 1',
        );
        $statement->execute([$userId]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    public function countFor(int $userId): int
    {
        $statement = $this->db->pdo()->prepare('SELECT COUNT(*) FROM diagnoses WHERE user_id = ?');
        $statement->execute([$userId]);
        return (int) $statement->fetchColumn();
    }

    private static function toJson(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
