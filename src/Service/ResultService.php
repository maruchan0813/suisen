<?php

declare(strict_types=1);

namespace Suisen\Service;

use Suisen\AppException;
use Suisen\Core\Senior;
use Suisen\Policy;
use Suisen\Repository\DiagnosisRepository;
use Suisen\Repository\SeniorRepository;

/**
 * 診断結果の画面（S5）に出す中身をまとめる。
 */
final class ResultService
{
    public function __construct(
        private readonly DiagnosisRepository $diagnoses,
        private readonly SeniorRepository $seniors,
        private readonly Policy $policy,
        private readonly string $kctCareersFile,
    ) {
    }

    /** @return array<string, mixed> */
    public function resultOf(int $userId, int $conversationId): array
    {
        $diagnosis = $this->diagnoses->findFor($conversationId, $userId) ?? throw new AppException('not_found', 404);

        return [
            'conversation_id' => $conversationId,
            'course_code'     => $diagnosis['course_code'],
            'course_name'     => $diagnosis['course_name'],
            'catch'           => $this->policy->courseCatch($diagnosis['course_code']),
            'diagnosed_at'    => $diagnosis['created_at'],
            'interests'       => $this->scoresOf($diagnosis['features'], Policy::GROUP_INTEREST),
            'styles'          => $this->scoresOf($diagnosis['features'], Policy::GROUP_STYLE),
            'messages'        => array_values(array_filter([$diagnosis['recommendation_text'], $diagnosis['career_text']])),
            'sources'         => $diagnosis['sources'],
            'careers'         => array_map(fn (Senior $senior): array => [
                'type'        => $this->policy->message('career_type_' . $senior->careerType),
                'is_study'    => $senior->careerType === Senior::CAREER_FURTHER_STUDY,
                'destination' => $senior->destination,
                'course_name' => $senior->courseName,
            ], $this->seniors->findByIds($diagnosis['senior_ids'])),
            'kct'             => $this->kctCareersOf($diagnosis['course_code']),
        ];
    }

    /**
     * @param array<string, float> $features
     * @return list<array{label: string, score: float}>
     */
    private function scoresOf(array $features, string $group): array
    {
        return array_map(fn (string $key): array => [
            'label' => $this->policy->featureLabel($key),
            'score' => (float) ($features[$key] ?? $this->policy->int('NEUTRAL_FEATURE_VALUE')),
        ], $this->policy->featureKeys($group));
    }

    /** 北九州高専の進路実績（data/kct_careers.json）。そのコースがなければ null。 */
    private function kctCareersOf(string $courseCode): ?array
    {
        if (!is_file($this->kctCareersFile)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($this->kctCareersFile), true);
        return is_array($data[$courseCode] ?? null) ? $data[$courseCode] : null;
    }
}
