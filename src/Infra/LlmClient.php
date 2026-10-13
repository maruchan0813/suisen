<?php

declare(strict_types=1);

namespace Suisen\Infra;

/**
 * 学内 LLM（OpenAI 互換の Chat Completions API）を呼ぶ。
 * プロンプトや温度は知らない。呼ぶ側が渡す。
 */
final class LlmClient
{
    private const HTTP_OK = 200;
    private const TIME_LIMIT_MARGIN_SECONDS = 30;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $connectTimeoutSeconds,
        private readonly int $timeoutSeconds,
    ) {
    }

    /**
     * @param list<array{role: string, content: string}> $messages
     * @param array<string, mixed>|null $responseFormat 返事の形の指定（json_schema など）
     * @throws LlmUnavailableException
     */
    public function chat(array $messages, float $temperature, int $maxTokens, ?array $responseFormat = null): string
    {
        $payload = [
            'model'       => $this->model,
            'messages'    => $messages,
            'temperature' => $temperature,
            'max_tokens'  => $maxTokens,
            // Qwen3 は既定で「考えてから答える」ので、本文だけを返させる
            'chat_template_kwargs' => ['enable_thinking' => false],
        ];
        if ($responseFormat !== null) {
            $payload['response_format'] = $responseFormat;
        }

        return $this->readContent($this->post('/chat/completions', $payload));
    }

    /** @param array<string, mixed> $payload */
    private function post(string $path, array $payload): string
    {
        // Windows（XAMPP）では PHP の実行時間の上限が待ち時間も数えるので、LLM を待つ間に打ち切られないようにする
        set_time_limit($this->timeoutSeconds + self::TIME_LIMIT_MARGIN_SECONDS);

        $curl = curl_init(rtrim($this->baseUrl, '/') . $path);
        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $this->apiKey],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
            CURLOPT_TIMEOUT        => $this->timeoutSeconds,
        ]);
        $body = curl_exec($curl);

        if ($body === false) {
            throw new LlmUnavailableException('LLM との通信に失敗しました：' . curl_error($curl));
        }
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($status !== self::HTTP_OK) {
            throw new LlmUnavailableException("LLM が HTTP {$status} を返しました：" . mb_substr((string) $body, 0, 300));
        }
        return (string) $body;
    }

    private function readContent(string $body): string
    {
        $data = json_decode($body, true);
        $content = is_array($data) ? ($data['choices'][0]['message']['content'] ?? null) : null;
        if (!is_string($content) || trim($content) === '') {
            throw new LlmUnavailableException('LLM の返事に本文がありません。');
        }
        return trim($content);
    }
}
