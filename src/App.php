<?php

declare(strict_types=1);

namespace Suisen;

use Suisen\Core\CourseRecommender;
use Suisen\Core\SeniorFinder;
use Suisen\Infra\Database;
use Suisen\Infra\LlmClient;
use Suisen\Infra\LogMailer;
use Suisen\Infra\Mailer;
use Suisen\Infra\Session;
use Suisen\Infra\SmtpMailer;
use Suisen\Repository\ConversationRepository;
use Suisen\Repository\CourseRepository;
use Suisen\Repository\DiagnosisRepository;
use Suisen\Repository\LoginCodeRepository;
use Suisen\Repository\MessageRepository;
use Suisen\Repository\SearchIndexRepository;
use Suisen\Repository\SeniorRepository;
use Suisen\Repository\UserRepository;
use Suisen\Service\ChatService;
use Suisen\Service\ConversationAccess;
use Suisen\Service\DiagnosisService;
use Suisen\Service\KnowledgeSearch;
use Suisen\Service\SearchPlanner;
use Suisen\Service\LoginService;
use Suisen\Service\ResultService;
use Suisen\Service\StatusService;

/**
 * 部品を組み立てる場所。環境設定（env）と方針（policy）は、ここで機構に渡す。
 * DB には必要になったときに初めて接続する。
 */
final class App
{
    public const SEARCH_INDEX_FILE = 'data/search_index.json';

    private ?Database $database = null;

    /** @param array<string, mixed> $env config/env.php の値 */
    public function __construct(
        public readonly array $env,
        public readonly Policy $policy,
        public readonly Session $session,
        public readonly string $rootDir,
    ) {
    }

    public function loginService(): LoginService
    {
        return new LoginService(
            new UserRepository($this->database()),
            new LoginCodeRepository($this->database()),
            $this->mailer(),
            $this->session,
            $this->policy,
        );
    }

    public function chatService(): ChatService
    {
        return new ChatService(
            $this->database(),
            new ConversationRepository($this->database()),
            new MessageRepository($this->database()),
            $this->courseRepository(),
            $this->conversationAccess(),
            new SearchPlanner($this->llm(), $this->policy),
            $this->knowledgeSearch(),
            $this->llm(),
            $this->policy,
        );
    }

    public function diagnosisService(): DiagnosisService
    {
        return new DiagnosisService(
            $this->database(),
            new ConversationRepository($this->database()),
            new MessageRepository($this->database()),
            new DiagnosisRepository($this->database()),
            $this->courseRepository(),
            new SeniorRepository($this->database(), $this->policy->featureKeys()),
            $this->conversationAccess(),
            new CourseRecommender(
                $this->policy->featureKeys(Policy::GROUP_INTEREST),
                $this->policy->featureKeys(Policy::GROUP_STYLE),
                $this->policy->float('INTEREST_WEIGHT'),
                $this->policy->float('STYLE_WEIGHT'),
            ),
            new SeniorFinder($this->policy->featureKeys(), $this->policy->int('SIMILAR_SENIOR_COUNT')),
            $this->knowledgeSearch(),
            $this->llm(),
            $this->policy,
        );
    }

    public function resultService(): ResultService
    {
        return new ResultService(
            new DiagnosisRepository($this->database()),
            new SeniorRepository($this->database(), $this->policy->featureKeys()),
            $this->policy,
            $this->rootDir . '/data/kct_careers.json',
        );
    }

    public function statusService(): StatusService
    {
        return new StatusService(
            new DiagnosisRepository($this->database()),
            new ConversationRepository($this->database()),
        );
    }

    private function database(): Database
    {
        return $this->database ??= Database::connect($this->env);
    }

    /** 検索の索引をファイルから読む。チャットと診断のときだけ読む（重いため）。 */
    private function knowledgeSearch(): KnowledgeSearch
    {
        $loaded = (new SearchIndexRepository($this->rootDir . '/' . self::SEARCH_INDEX_FILE))->load();
        return new KnowledgeSearch($loaded['vectorizer'], $loaded['index'], $this->policy->float('RAG_MIN_SCORE'));
    }

    private function courseRepository(): CourseRepository
    {
        return new CourseRepository($this->database(), $this->policy->featureKeys());
    }

    private function conversationAccess(): ConversationAccess
    {
        return new ConversationAccess(new ConversationRepository($this->database()), $this->policy);
    }

    private function llm(): LlmClient
    {
        return new LlmClient(
            $this->env['LLM_BASE_URL'],
            $this->env['LLM_API_KEY'],
            $this->env['LLM_MODEL'],
            $this->policy->int('LLM_CONNECT_TIMEOUT_SECONDS'),
            $this->policy->int('LLM_TIMEOUT_SECONDS'),
        );
    }

    private function mailer(): Mailer
    {
        if ($this->env['MAIL_DRIVER'] === 'log') {
            return new LogMailer($this->rootDir . '/storage/mail.log');
        }
        return new SmtpMailer(
            $this->env['SMTP_HOST'],
            (int) $this->env['SMTP_PORT'],
            $this->env['SMTP_USER'],
            $this->env['SMTP_APP_PASSWORD'],
            $this->env['MAIL_FROM_NAME'],
        );
    }
}
