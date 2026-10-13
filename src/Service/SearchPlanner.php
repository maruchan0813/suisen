<?php

declare(strict_types=1);

namespace Suisen\Service;

use JsonException;
use Suisen\Core\Course;
use Suisen\Core\PromptTemplate;
use Suisen\Infra\LlmClient;
use Suisen\Infra\LlmUnavailableException;
use Suisen\Policy;
use Suisen\Repository\MessageRepository;

/**
 * 学内 LLM は自分で検索できないので、「検索が必要か」と「何で検索するか」だけを考えさせる。
 * TF-IDF は言い換えに弱いので、LLM が考えたキーワード（言い換えを含む）で補う。
 */
final class SearchPlanner
{
    private const SCHEMA_NAME = 'search_plan';
    private const MAX_KEYWORDS = 5;
    /** 質問の形の文（「？」で終わる、「〜か」「〜の」で終わる、「教えて」「知りたい」などで終わる） */
    private const QUESTION_FORM_PATTERN = '/([？?]|[かの]|教えて(ください|ほしい(です)?)?|知りたい(です)?|聞きたい(です)?)[。．.！!]?\s*$/u';
    private const GRADES = [1, 2, 3, 4, 5];

    public function __construct(
        private readonly LlmClient $llm,
        private readonly Policy $policy,
    ) {
    }

    /**
     * @param list<array{role: string, content: string}> $history
     * @param list<Course> $courses
     * @throws LlmUnavailableException
     */
    public function plan(array $history, string $message, array $courses): SearchPlan
    {
        $prompt = PromptTemplate::render($this->policy->prompt('plan_search'), [
            'courses'      => implode("\n", array_map(static fn (Course $course): string => "- {$course->code}：{$course->name}コース", $courses)),
            'conversation' => self::conversationText(array_slice($history, -$this->policy->int('RAG_PLAN_HISTORY_MESSAGES'))),
            'message'      => $message,
        ]);
        $reply = $this->llm->chat(
            [['role' => 'user', 'content' => $prompt]],
            $this->policy->float('LLM_PLAN_TEMPERATURE'),
            $this->policy->int('LLM_PLAN_MAX_TOKENS'),
            $this->responseFormat(array_map(static fn (Course $course): string => $course->code, $courses)),
        );

        try {
            $raw = json_decode($reply, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $raw = null;
        }
        if (!is_array($raw)) {
            // 計画を読めなくても答えは返したいので、学生のメッセージそのままで検索する
            return new SearchPlan(true, $message, null);
        }
        return $this->toPlan($raw, $message, $courses);
    }

    /**
     * LLM の出力を信用しすぎず、形を確かめてから使う。
     *
     * @param array<mixed> $raw
     * @param list<Course> $courses
     */
    private function toPlan(array $raw, string $message, array $courses): SearchPlan
    {
        $keywords = array_filter(
            is_array($raw['keywords'] ?? null) ? $raw['keywords'] : [],
            static fn (mixed $keyword): bool => is_string($keyword) && trim($keyword) !== '',
        );
        $keywords = array_slice(array_values($keywords), 0, self::MAX_KEYWORDS);

        $validCodes = array_map(static fn (Course $course): string => $course->code, $courses);
        $requested = array_values(array_intersect(is_array($raw['courses'] ?? null) ? $raw['courses'] : [], $validCodes));

        return new SearchPlan(
            needsSearch: ($raw['needs_search'] ?? false) === true,
            query: trim(implode(' ', $keywords) . ' ' . $message),
            courseCode: count($requested) === 1 ? $requested[0] : null, // 複数・指定なしは全コースから探す
            // 「〜してみたいです」などの話を質問と取り違えることがあるので、文の形でも確かめる
            studentAsked: ($raw['student_asked'] ?? false) === true && self::looksLikeQuestion($message),
            grades: array_values(array_unique(array_intersect(
                array_map('intval', is_array($raw['grades'] ?? null) ? $raw['grades'] : []),
                self::GRADES,
            ))),
        );
    }

    public static function looksLikeQuestion(string $message): bool
    {
        return preg_match(self::QUESTION_FORM_PATTERN, trim($message)) === 1;
    }

    /** @param list<string> $courseCodes */
    private function responseFormat(array $courseCodes): array
    {
        return [
            'type'        => 'json_schema',
            'json_schema' => [
                'name'   => self::SCHEMA_NAME,
                'strict' => true,
                'schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'needs_search'  => ['type' => 'boolean'],
                        'student_asked' => ['type' => 'boolean'],
                        'keywords'     => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => self::MAX_KEYWORDS],
                        'courses'      => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => $courseCodes]],
                        'grades'       => ['type' => 'array', 'items' => ['type' => 'integer', 'enum' => self::GRADES]],
                    ],
                    'required'             => ['needs_search', 'student_asked', 'keywords', 'courses', 'grades'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /** @param list<array{role: string, content: string}> $messages */
    private static function conversationText(array $messages): string
    {
        return implode("\n", array_map(
            static fn (array $message): string => ($message['role'] === MessageRepository::ROLE_USER ? '学生' : 'AI') . '：' . $message['content'],
            $messages,
        ));
    }
}
