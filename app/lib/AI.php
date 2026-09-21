<?php
declare(strict_types=1);

/**
 * Server-side AI client. The API key never reaches the browser.
 * Providers: Anthropic Claude, OpenAI, and any OpenAI-compatible endpoint.
 */
final class AI
{
    public const PROVIDERS = [
        'anthropic' => ['label' => 'Anthropic Claude', 'model' => 'claude-sonnet-5'],
        'openai'    => ['label' => 'OpenAI', 'model' => 'gpt-4.1-mini'],
        'gemini'    => ['label' => 'Google Gemini', 'model' => 'gemini-2.5-flash'],
    ];

    public static function configured(): bool
    {
        return setting('ai_enabled') === '1' && setting('ai_key', '') !== '';
    }

    public static function provider(): string
    {
        $p = (string) setting('ai_provider', 'anthropic');
        return isset(self::PROVIDERS[$p]) ? $p : 'anthropic';
    }

    public static function model(): string
    {
        $m = trim((string) setting('ai_model', ''));
        return $m !== '' ? $m : self::PROVIDERS[self::provider()]['model'];
    }

    /**
     * @param array<int, array{role:string, content:string}> $messages
     * @throws RuntimeException with a message safe to show the user
     */
    public static function complete(string $system, array $messages, int $maxTokens = 1600, ?array $override = null): string
    {
        $provider = $override['provider'] ?? self::provider();
        $model    = $override['model'] ?? self::model();
        $key      = $override['key'] ?? Crypto::decrypt((string) setting('ai_key', ''));
        if ($key === '') {
            throw new RuntimeException('The AI assistant is not set up yet. An administrator can add an API key in Settings.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The PHP cURL extension is disabled on this server. Enable it in hPanel → PHP Configuration.');
        }

        $messages = array_values(array_filter(array_map(static fn ($m) => [
            'role'    => $m['role'] === 'assistant' ? 'assistant' : 'user',
            'content' => mb_substr(trim((string) $m['content']), 0, 20000),
        ], $messages), static fn ($m) => $m['content'] !== ''));

        return match ($provider) {
            'openai' => self::openai($key, $model, $system, $messages, $maxTokens),
            'gemini' => self::gemini($key, $model, $system, $messages, $maxTokens),
            default  => self::anthropic($key, $model, $system, $messages, $maxTokens),
        };
    }

    private static function anthropic(string $key, string $model, string $system, array $messages, int $max): string
    {
        $res = self::post('https://api.anthropic.com/v1/messages', [
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
        ], [
            'model'      => $model,
            'max_tokens' => $max,
            'system'     => $system,
            'messages'   => $messages,
        ]);
        $text = '';
        foreach ($res['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
        return trim($text);
    }

    private static function openai(string $key, string $model, string $system, array $messages, int $max): string
    {
        $res = self::post('https://api.openai.com/v1/chat/completions', [
            'Authorization: Bearer ' . $key,
        ], [
            'model'                 => $model,
            'max_completion_tokens' => $max,
            'messages'              => [['role' => 'system', 'content' => $system], ...$messages],
        ]);
        return trim((string) ($res['choices'][0]['message']['content'] ?? ''));
    }

    private static function gemini(string $key, string $model, string $system, array $messages, int $max): string
    {
        $contents = array_map(static fn ($m) => [
            'role'  => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ], $messages);
        $res = self::post('https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent', [
            'x-goog-api-key: ' . $key,
        ], [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents'          => $contents,
            'generationConfig'  => ['maxOutputTokens' => $max],
        ]);
        $text = '';
        foreach ($res['candidates'][0]['content']['parts'] ?? [] as $p) {
            $text .= $p['text'] ?? '';
        }
        return trim($text);
    }

    private static function post(string $url, array $headers, array $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', ...$headers],
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        ]);
        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Could not reach the AI provider (' . $err . '). Check the server internet connection and try again.');
        }
        $json = json_decode((string) $raw, true) ?: [];
        if ($status >= 400) {
            $msg = $json['error']['message'] ?? ($json['error']['status'] ?? ($json[0]['error']['message'] ?? 'Unknown error'));
            throw new RuntimeException(match (true) {
                $status === 401 || $status === 403 => 'The AI provider rejected the API key. Check the key in Settings.',
                $status === 404                    => 'The AI model was not found. Check the model name in Settings. Provider said: ' . $msg,
                $status === 429                    => 'The AI provider rate limit or credit balance was reached. Try again shortly or check your account billing.',
                default                            => 'The AI provider returned an error: ' . $msg,
            });
        }
        return $json;
    }
}
