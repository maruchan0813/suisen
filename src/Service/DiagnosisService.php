<?php

declare(strict_types=1);

namespace Suisen\Service;

use JsonException;
use Suisen\AppException;
use Suisen\Core\Course;
use Suisen\Core\CourseRanking;
use Suisen\Core\CourseRecommender;
use Suisen\Core\CourseScore;
use Suisen\Core\FeatureVector;
use Suisen\Core\PromptTemplate;
use Suisen\Core\Search\SearchHit;
use Suisen\Core\Senior;
use Suisen\Core\SeniorFinder;
use Suisen\Infra\Database;
use Suisen\Infra\LlmClient;
use Suisen\Infra\LlmUnavailableException;
use Suisen\Policy;
use Suisen\Repository\ConversationRepository;
use Suisen\Repository\CourseRepository;
use Suisen\Repository\DiagnosisRepository;
use Suisen\Repository\MessageRepository;
use Suisen\Repository\SeniorRepository;

/**
 * 会話から学生の特徴を取り出し、コースを推薦し、似た先輩の進路を紹介する。
 */
final class DiagnosisService
{
    private const FEATURE_SCHEMA_NAME = 'student_features';

    public function __construct(
        private readonly Database $db,
        private readonly ConversationRepository $conversations,
        private readonly MessageRepository $messages,
        private readonly DiagnosisRepository $diagnoses,
        private readonly CourseRepository $courses,
        private readonly SeniorRepository $seniors,
        private readonly ConversationAccess $access,
        private readonly CourseRecommender $recommender,
        private readonly SeniorFinder $seniorFinder,
        private readonly KnowledgeSearch $knowledge,
        private readonly LlmClient $llm,
        private readonly Policy $policy,
    ) {
    }

    /** @return array{course_name: string, recommendation_text: string, career_text: string, sources: list<string>} */
    public function diagnose(int $userId, int $conversationId): array
    {
        $this->access->requireActive($userId, $conversationId);
        $history = $this->messages->listFor($conversationId);
        $this->assertEnoughTurns($history);

        $features = $this->extractFeatures($history);
        $ranking = $this->recommender->rank($features, $this->courses->all());
        $similarSeniors = $this->seniorFinder->findSimilar($features, $this->seniors->all());
        $best = $ranking->best();
        $subjectHits = $this->searchSubjectsFor($best, $history);
        $careerHits = $this->searchCareerReferences($best->course, $similarSeniors);
        $recommendationText = $this->writeRecommendation($best, $subjectHits);
        $careerText = $this->writeCareerStory($best->course, $similarSeniors, $careerHits);

        $sources = KnowledgeSearch::headings([...$subjectHits, ...$careerHits]);
        $this->saveResult($userId, $conversationId, $features, $ranking, $similarSeniors, $recommendationText, $careerText, $sources);

        return [
            'redirect'            => "/result/{$conversationId}",
            'course_name'         => $best->course->name,
            'recommendation_text' => $recommendationText,
            'career_text'         => $careerText,
            'sources'             => $sources,
        ];
    }

    /**
     * 推薦したコースの中から、推薦の理由の項目に関わる授業を探す。
     * 理由がないとき（全部「ふつう」など）だけ、学生が話したことで探す。
     * 学生の発言には別のコースへの質問なども混ざり、検索がぶれるため、理由があれば理由だけを使う。
     *
     * @param list<array{role: string, content: string}> $history
     * @return list<SearchHit>
     */
    private function searchSubjectsFor(CourseScore $best, array $history): array
    {
        $reasonLabels = array_map(
            fn (string $key): string => $this->policy->featureLabel($key),
            $best->reasonKeys($this->policy->int('RECOMMENDATION_REASON_COUNT')),
        );
        $studentWords = array_map(
            static fn (array $message): string => $message['content'],
            array_filter($history, static fn (array $message): bool => $message['role'] === MessageRepository::ROLE_USER),
        );
        return $this->knowledge->search(
            implode(' ', $reasonLabels === [] ? $studentWords : $reasonLabels),
            $best->course->code,
            $this->policy->int('RAG_RECOMMEND_SUBJECT_COUNT'),
            includeShared: false, // 「このコースでは〜」と言うので、全コース共通の授業は挙げない
        );
    }

    /**
     * 推薦したコースの中から、似た先輩の進路に関わる授業を探す。
     *
     * @param list<Senior> $similarSeniors
     * @return list<SearchHit>
     */
    private function searchCareerReferences(Course $bestCourse, array $similarSeniors): array
    {
        $destinations = array_map(static fn (Senior $senior): string => $senior->destination, $similarSeniors);
        return $this->knowledge->search(
            implode(' ', $destinations),
            $bestCourse->code,
            $this->policy->int('RAG_CAREER_TOP_K'),
            includeShared: false,
        );
    }

    /** @param list<array{role: string, content: string}> $history */
    private function assertEnoughTurns(array $history): void
    {
        if (!$this->access->canDiagnose(ConversationAccess::countTurns($history))) {
            throw new AppException('not_enough_turns', 409);
        }
    }

    /** @param list<array{role: string, content: string}> $history */
    private function extractFeatures(array $history): FeatureVector
    {
        $prompt = PromptTemplate::render($this->policy->prompt('extract_features'), [
            'min'                 => $this->policy->int('FEATURE_MIN'),
            'max'                 => $this->policy->int('FEATURE_MAX'),
            'neutral'             => $this->policy->int('NEUTRAL_FEATURE_VALUE'),
            'feature_definitions' => $this->featureDefinitionLines(),
            'conversation'        => $this->conversationText($history),
        ]);
        $reply = $this->askLlm(
            [['role' => 'user', 'content' => $prompt]],
            $this->policy->float('LLM_EXTRACT_TEMPERATURE'),
            $this->policy->int('LLM_EXTRACT_MAX_TOKENS'),
            $this->featureResponseFormat(),
        );

        try {
            $raw = json_decode($reply, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log("特徴の JSON を読めません：{$reply}");
            throw new AppException('llm_unavailable', 503, previous: $e);
        }

        // スキーマで縛っても想定外の値は来うるので、ここでもう一度検査する
        return FeatureVector::sanitize(
            is_array($raw) ? $raw : [],
            $this->policy->featureKeys(),
            $this->policy->int('FEATURE_MIN'),
            $this->policy->int('FEATURE_MAX'),
            $this->policy->int('NEUTRAL_FEATURE_VALUE'),
        );
    }

    /** 返事の形を、13項目それぞれ決まった整数のどれか、に縛る。項目は方針から作る。 */
    private function featureResponseFormat(): array
    {
        $allowedValues = range($this->policy->int('FEATURE_MIN'), $this->policy->int('FEATURE_MAX'));
        $keys = $this->policy->featureKeys();
        $properties = array_fill_keys($keys, ['type' => 'integer', 'enum' => $allowedValues]);

        return [
            'type'        => 'json_schema',
            'json_schema' => [
                'name'   => self::FEATURE_SCHEMA_NAME,
                'strict' => true,
                'schema' => [
                    'type'                 => 'object',
                    'properties'           => $properties,
                    'required'             => $keys,
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * 推薦の文章は LLM を使わず、テンプレートで作る（根拠を毎回同じ形で示すため）。
     * 検索で見つかった授業があれば、科目名を添える。
     *
     * @param list<SearchHit> $subjectHits
     */
    private function writeRecommendation(CourseScore $best, array $subjectHits): string
    {
        $text = $this->recommendationReasonText($best);
        if ($subjectHits === []) {
            return $text;
        }
        $subjects = array_map(static fn (SearchHit $hit): string => "「{$hit->chunk->title}」", $subjectHits);
        return $text . $this->policy->message('recommendation_subjects', [
            'subjects' => implode($this->policy->message('recommendation_subject_joiner'), $subjects),
        ]);
    }

    private function recommendationReasonText(CourseScore $best): string
    {
        $reasonLabels = array_map(
            fn (string $key): string => "「{$this->policy->featureLabel($key)}」",
            $best->reasonKeys($this->policy->int('RECOMMENDATION_REASON_COUNT')),
        );
        if ($reasonLabels === []) {
            return $this->policy->message('recommendation_without_reason', ['course' => $best->course->name]);
        }
        return $this->policy->message('recommendation', [
            'course'  => $best->course->name,
            'reasons' => implode('', $reasonLabels),
        ]);
    }

    /**
     * @param list<Senior> $similarSeniors
     * @param list<SearchHit> $references
     */
    private function writeCareerStory(Course $bestCourse, array $similarSeniors, array $references): string
    {
        $prompt = PromptTemplate::render($this->policy->prompt('career_story'), [
            'best_course' => "{$bestCourse->name}コース",
            'seniors'     => $this->seniorLines($similarSeniors),
            'references'  => KnowledgeSearch::referenceText($references) ?: $this->policy->message('no_references'),
        ]);
        return $this->askLlm(
            [['role' => 'user', 'content' => $prompt]],
            $this->policy->float('LLM_CAREER_TEMPERATURE'),
            $this->policy->int('LLM_CAREER_MAX_TOKENS'),
        );
    }

    /**
     * @param list<Senior> $similarSeniors
     */
    private function saveResult(
        int $userId,
        int $conversationId,
        FeatureVector $features,
        CourseRanking $ranking,
        array $similarSeniors,
        string $recommendationText,
        string $careerText,
        array $sources,
    ): void {
        $this->db->transaction(function () use (
            $userId, $conversationId, $features, $ranking, $similarSeniors, $recommendationText, $careerText, $sources
        ): void {
            $this->messages->add($conversationId, MessageRepository::ROLE_ASSISTANT, $recommendationText);
            $this->messages->add($conversationId, MessageRepository::ROLE_ASSISTANT, $careerText);
            $this->diagnoses->save(
                conversationId: $conversationId,
                userId: $userId,
                features: $features->toArray(),
                courseId: $ranking->best()->course->id,
                courseScores: $ranking->totalsByCourseCode(),
                seniorIds: array_map(static fn (Senior $senior): int => $senior->id, $similarSeniors),
                recommendationText: $recommendationText,
                careerText: $careerText,
                sources: $sources,
            );
            $this->conversations->markDiagnosed($conversationId);
        });
    }

    /**
     * @param list<array{role: string, content: string}> $messages
     * @param array<string, mixed>|null $responseFormat
     */
    private function askLlm(array $messages, float $temperature, int $maxTokens, ?array $responseFormat = null): string
    {
        try {
            return $this->llm->chat($messages, $temperature, $maxTokens, $responseFormat);
        } catch (LlmUnavailableException $e) {
            error_log((string) $e);
            throw new AppException('llm_unavailable', 503, previous: $e);
        }
    }

    private function featureDefinitionLines(): string
    {
        return implode("\n", array_map(
            fn (string $key): string => "- {$key}：{$this->policy->featureLabel($key)}",
            $this->policy->featureKeys(),
        ));
    }

    /** @param list<array{role: string, content: string}> $history */
    private function conversationText(array $history): string
    {
        return implode("\n", array_map(
            static fn (array $message): string => ($message['role'] === MessageRepository::ROLE_USER ? '学生' : 'AI')
                . '：' . $message['content'],
            $history,
        ));
    }

    /** @param list<Senior> $similarSeniors */
    private function seniorLines(array $similarSeniors): string
    {
        return implode("\n", array_map(
            fn (Senior $senior): string => sprintf(
                '  - %sコースに進み、%s：%s',
                $senior->courseName,
                $this->policy->message('career_type_' . $senior->careerType),
                $senior->destination,
            ),
            $similarSeniors,
        ));
    }
}
