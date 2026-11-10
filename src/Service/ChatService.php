<?php

declare(strict_types=1);

namespace Suisen\Service;

use Suisen\AppException;
use Suisen\Core\FollowUpRule;
use Suisen\Core\Confidence;
use Suisen\Core\NextTopicPicker;
use Suisen\Core\PromptTemplate;
use Suisen\Core\ReplyReview;
use Suisen\Core\Search\SearchHit;
use Suisen\Infra\Database;
use Suisen\Infra\LlmClient;
use Suisen\Infra\LlmUnavailableException;
use Suisen\Policy;
use Suisen\Repository\ConversationRepository;
use Suisen\Repository\CourseRepository;
use Suisen\Repository\MessageRepository;

/**
 * 学生と LLM の会話。
 */
final class ChatService
{
    public function __construct(
        private readonly Database $db,
        private readonly ConversationRepository $conversations,
        private readonly MessageRepository $messages,
        private readonly CourseRepository $courses,
        private readonly ConversationAccess $access,
        private readonly SearchPlanner $planner,
        private readonly KnowledgeSearch $knowledge,
        private readonly LlmClient $llm,
        private readonly Policy $policy,
        private readonly Confidence $confidence,
        private readonly ConfidencePresenter $presenter,
        private readonly TopicCoverage $coverage,
    ) {
    }

    /** 新しい会話を、決まったあいさつで始める（LLM は呼ばない）。 */
    public function start(int $userId): int
    {
        return $this->db->transaction(function () use ($userId): int {
            $conversationId = $this->conversations->create($userId);
            $this->messages->add($conversationId, MessageRepository::ROLE_ASSISTANT, $this->policy->message('chat_greeting'));
            return $conversationId;
        });
    }

    /**
     * 画面に出すための会話の中身。
     *
     * @return array{id: int, is_active: bool, messages: list<array{role: string, content: string}>, can_diagnose: bool, evidence: array<string, mixed>}
     */
    public function open(int $userId, int $conversationId): array
    {
        $conversation = $this->access->requireOwned($userId, $conversationId);
        $messages = $this->messages->listFor($conversationId);
        return [
            'id'           => (int) $conversation['id'],
            'is_active'    => $conversation['status'] === ConversationRepository::STATUS_ACTIVE,
            'messages'     => $messages,
            'can_diagnose' => $this->access->canDiagnose($conversationId, ConversationAccess::countTurns($messages)),
            'evidence'     => $this->evidenceOf($conversationId),
        ];
    }

    /** @return array{reply: string, turn_count: int, can_diagnose: bool, sources: list<string>, evidence: array<string, mixed>} */
    public function send(int $userId, int $conversationId, string $rawContent): array
    {
        $this->access->requireActive($userId, $conversationId);
        $content = $this->validateContent($rawContent);
        $history = $this->messages->listFor($conversationId);
        $turnCount = ConversationAccess::countTurns($history);
        if ($turnCount >= $this->policy->int('MAX_TURNS_PER_CONVERSATION')) {
            throw new AppException('turn_limit', 409);
        }

        $plan = $this->planSearch($history, $content);
        $references = $plan->needsSearch
            ? $this->knowledge->search($plan->query, $plan->courseCode, $this->policy->int('RAG_CHAT_TOP_K'), grades: $plan->grades)
            : [];
        $subjectList = $this->subjectListFor($plan);
        $askedQuestions = AskedQuestions::from($history);
        $followUpTopic = $plan->studentAsked ? null : $this->followUpTopic($conversationId, $content);
        $topic = $plan->studentAsked ? null : ($followUpTopic ?? $this->nextTopic($conversationId, $askedQuestions, $history, $content));
        $reply = $this->askForReply($history, $content, $references, $subjectList, $askedQuestions, $this->topicRecord($conversationId), $this->turnInstruction($plan->studentAsked, $topic, $followUpTopic !== null));

        // LLM の返事をもらえたときだけ、質問と返事を組で保存する（壊れた履歴を残さない）
        $this->db->transaction(function () use ($conversationId, $content, $reply, $topic): void {
            $this->messages->add($conversationId, MessageRepository::ROLE_USER, $content);
            $this->messages->add($conversationId, MessageRepository::ROLE_ASSISTANT, $reply, $topic);
        });

        $turnCount++;
        return [
            'reply'        => $reply,
            'turn_count'   => $turnCount,
            'can_diagnose' => $this->access->canDiagnose($conversationId, $turnCount),
            'evidence'     => $this->evidenceOf($conversationId),
            'sources'      => [...($subjectList === '' ? [] : [$this->subjectListSource($plan)]), ...KnowledgeSearch::headings($references)],
        ];
    }

    /** チャット中に出す「いまの情報量」。聞いた項目の数から決める（診断の前なので、点数の差は使えない）。 */
    private function evidenceOf(int $conversationId): array
    {
        $covered = $this->coverage->coveredCount($conversationId);
        $total = $this->coverage->totalCount();
        return $this->presenter->evidence($this->confidence->evidenceLevel($covered, $total), $covered, $total);
    }

    /**
     * 返事の根拠にするシラバスを探すか（RAG）と、学生のメッセージが質問かを LLM に判断させる。
     *
     * @param list<array{role: string, content: string}> $history
     */
    private function planSearch(array $history, string $content): SearchPlan
    {
        try {
            return $this->planner->plan($history, $content, $this->courses->all());
        } catch (LlmUnavailableException $e) {
            error_log((string) $e);
            throw new AppException('llm_unavailable', 503, previous: $e);
        }
    }

    private function validateContent(string $rawContent): string
    {
        $content = trim($rawContent);
        $maxLength = $this->policy->int('MAX_MESSAGE_LENGTH');
        if (!mb_check_encoding($content, 'UTF-8') || $content === '' || mb_strlen($content) > $maxLength) {
            throw new AppException('invalid_content', params: ['max' => $maxLength]);
        }
        return $content;
    }

    /**
     * @param list<array{role: string, content: string}> $history
     * @param list<SearchHit> $references
     * @param list<string> $askedQuestions
     */
    private function askForReply(array $history, string $content, array $references, string $subjectList, array $askedQuestions, string $topicRecord, string $turnInstruction): string
    {
        $review = new ReplyReview(
            $this->knowledge->subjectTitles(),
            $this->policy->float('REPLY_REPEAT_SIMILARITY'),
            $this->policy->list('REPLY_FORBIDDEN_PHRASES'),
        );
        $revision = '';
        for ($attempt = 0; ; $attempt++) {
            $reply = $this->requestReply([
                ['role' => 'system', 'content' => $this->systemPrompt($references, $subjectList, $askedQuestions, $topicRecord, $turnInstruction, $revision)],
                ...$history,
                ['role' => MessageRepository::ROLE_USER, 'content' => $content],
            ]);
            // 指示だけでは守り切れないことをプログラムで確かめ、問題があれば理由を伝えて作り直させる
            $revision = $this->revisionFor($review, $reply, $askedQuestions, $content);
            if ($revision === '') {
                return $reply;
            }
            if ($attempt >= $this->policy->int('REPLY_MAX_REGENERATIONS')) {
                // 作り直しても問題が残ったら、問題のある文だけを取り除く
                // （似た質問は学生にもう聞いてあり、実在しない授業名は誤った情報になるため）
                $reply = $review->withoutSentencesMentioning($reply, $review->unknownSubjects($reply, $content));
                return $review->withoutRepeatedQuestions($reply, $askedQuestions);
            }
        }
    }

    /** @param list<array{role: string, content: string}> $messages */
    private function requestReply(array $messages): string
    {
        try {
            return $this->llm->chat(
                $messages,
                $this->policy->float('LLM_CHAT_TEMPERATURE'),
                $this->policy->int('LLM_CHAT_MAX_TOKENS'),
            );
        } catch (LlmUnavailableException $e) {
            error_log((string) $e);
            throw new AppException('llm_unavailable', 503, previous: $e);
        }
    }

    /**
     * 返事を作り直させるときの指示。問題がなければ空文字。
     *
     * @param list<string> $askedQuestions
     */
    private function revisionFor(ReplyReview $review, string $reply, array $askedQuestions, string $content): string
    {
        $lines = [];
        $unknownSubjects = $review->unknownSubjects($reply, $content);
        if ($unknownSubjects !== []) {
            $lines[] = $this->policy->message('revision_unknown_subjects', ['subjects' => '「' . implode('」「', $unknownSubjects) . '」']);
        }
        $forbidden = $review->forbiddenPhrasesIn($reply);
        if ($forbidden !== []) {
            $lines[] = $this->policy->message('revision_forbidden_phrases', ['phrases' => '「' . implode('」「', $forbidden) . '」']);
        }
        $repeated = $review->repeatedQuestions($reply, $askedQuestions);
        if ($repeated !== []) {
            $lines[] = $this->policy->message('revision_repeated_questions', ['questions' => '「' . implode('」「', $repeated) . '」']);
        }
        if ($lines === []) {
            return '';
        }
        error_log('返事を作り直します：' . implode(' / ', $lines));
        return $this->policy->message('revision_header') . "\n" . implode("\n", array_map(static fn (string $line): string => "- {$line}", $lines));
    }

    /**
     * @param list<SearchHit> $references
     * @param list<string> $askedQuestions これまでに AI がした質問（同じ質問を繰り返させないため）
     */
    private function systemPrompt(array $references, string $subjectList, array $askedQuestions, string $topicRecord, string $turnInstruction, string $revision = ''): string
    {
        return PromptTemplate::render($this->policy->prompt('chat_system'), [
            'interest_items'   => implode('、', $this->policy->featureLabels(Policy::GROUP_INTEREST)),
            'style_items'      => implode('、', $this->policy->featureLabels(Policy::GROUP_STYLE)),
            'course_summaries' => $this->courseSummaryLines(),
            'references'       => KnowledgeSearch::referenceText($references) ?: $this->policy->message('no_references'),
            'subject_list'     => $subjectList ?: $this->policy->message('no_subject_list'),
            'asked_questions'  => $askedQuestions === []
                ? $this->policy->message('no_asked_questions')
                : implode("\n", array_map(static fn (string $question): string => "- {$question}", $askedQuestions)),
            'topic_record'     => $topicRecord,
            // 学生が質問したあとに、答えてもらえなかった前の質問へ戻るのを防ぐため、今回の返し方を決めて伝える
            'turn_instruction' => $turnInstruction,
            'revision'         => $revision,
        ]);
    }

    /** 項目のうち、もう聞いたものと、まだ聞いていないもの（AI が「まだ聞けていない項目を優先して聞く」ために渡す）。 */
    private function topicRecord(int $conversationId): string
    {
        $asked = $this->messages->askedTopicsFor($conversationId);
        $remaining = array_values(array_diff($this->policy->featureLabels(), $asked));
        return $this->policy->message('topic_record', [
            'asked'     => $asked === [] ? $this->policy->message('topic_record_none') : implode('、', $asked),
            'remaining' => $remaining === [] ? $this->policy->message('topic_record_none') : implode('、', $remaining),
        ]);
    }

    /** 学年が指定されたときだけ、そのコース・学年の授業一覧を作る（「3年の授業を教えて」に答えるため）。 */
    private function subjectListFor(SearchPlan $plan): string
    {
        if (!$plan->needsSearch || $plan->grades === []) {
            return '';
        }
        return $this->knowledge->subjectListText(
            $plan->courseCode,
            $plan->grades,
            $this->policy->int('RAG_SUBJECT_LIST_MAX'),
            $this->policy->int('RAG_SUBJECT_LIST_SUMMARY_CHARS'),
        );
    }

    /** 画面の「参考にしたシラバス」に出す、授業一覧の見出し。 */
    private function subjectListSource(SearchPlan $plan): string
    {
        $course = $plan->courseCode === null ? '全コース' : $this->courseName($plan->courseCode) . 'コース';
        return $this->policy->message('subject_list_source', [
            'course' => $course,
            'grades' => implode('・', array_map(static fn (int $grade): string => "{$grade}年", $plan->grades)),
        ]);
    }

    private function courseName(string $courseCode): string
    {
        foreach ($this->courses->all() as $course) {
            if ($course->code === $courseCode) {
                return $course->name;
            }
        }
        return $courseCode;
    }

    /**
     * 次に聞く項目。LLM に任せると同じ質問を繰り返すので、こちらで決める。
     *
     * @param list<string> $askedQuestions
     * @param list<array{role: string, content: string}> $history
     */
    private function nextTopic(int $conversationId, array $askedQuestions, array $history, string $content): ?string
    {
        $studentWords = array_map(
            static fn (array $message): string => $message['content'],
            array_filter($history, static fn (array $message): bool => $message['role'] === MessageRepository::ROLE_USER),
        );
        return (new NextTopicPicker($this->policy->featureLabels()))->pick(
            [...$askedQuestions, ...$studentWords, $content],
            $this->messages->askedTopicsFor($conversationId),
        );
    }

    /** 学生の答えが話の広がりそうなものなら、前に聞いた項目をもう一歩くわしく聞く。深掘りしないなら null。 */
    private function followUpTopic(int $conversationId, string $content): ?string
    {
        return (new FollowUpRule(
            $this->policy->int('FOLLOW_UP_MAX_TURNS'),
            $this->policy->int('FOLLOW_UP_MIN_ANSWER_CHARS'),
            $this->policy->list('FOLLOW_UP_SKIP_PHRASES'),
            $this->policy->int('FOLLOW_UP_EVERY_NTH_TOPIC'),
        ))->topicToDeepen($this->messages->assistantTopicsFor($conversationId), $content);
    }

    /** 今回の返し方。質問されたら答えるだけ。それ以外は、深掘りか、決めた項目について聞かせる。 */
    private function turnInstruction(bool $studentAsked, ?string $topic, bool $followUp): string
    {
        if ($studentAsked) {
            return $this->policy->message('turn_instruction_answer_only');
        }
        if ($followUp && $topic !== null) {
            return $this->policy->message('turn_instruction_follow_up', ['topic' => $topic]);
        }
        return $topic === null
            ? $this->policy->message('turn_instruction_ask_free')
            : $this->policy->message('turn_instruction_ask_topic', ['topic' => $topic]);
    }

    private function courseSummaryLines(): string
    {
        $lines = [];
        foreach ($this->courses->all() as $course) {
            $lines[] = "  - {$course->name}コース：" . $this->policy->courseSummary($course->code);
        }
        return implode("\n", $lines);
    }
}
