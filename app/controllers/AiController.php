<?php
declare(strict_types=1);

final class AiController
{
    public function chat(): void
    {
        $u = Auth::require('assistant');
        if (!AI::configured()) {
            json_out(['ok' => false, 'error' => Auth::isAdmin()
                ? 'Add an AI API key in Settings → AI assistant to start using the assistant.'
                : 'The AI assistant is not set up yet. Ask an administrator to add an API key.'], 400);
        }
        if (!RateLimit::aiAllowed((int) $u['id'])) {
            json_out(['ok' => false, 'error' => 'You have sent many requests in a short time. Wait a few minutes and try again.'], 429);
        }

        $body = json_body();
        $messages = [];
        foreach (array_slice((array) ($body['messages'] ?? []), -16) as $m) {
            if (is_array($m) && isset($m['role'], $m['content']) && is_string($m['content'])) {
                $messages[] = ['role' => $m['role'] === 'assistant' ? 'assistant' : 'user', 'content' => mb_substr($m['content'], 0, 12000)];
            }
        }
        if (!$messages || end($messages)['role'] !== 'user') {
            json_out(['ok' => false, 'error' => 'Type a question first.'], 422);
        }
        $page = is_array($body['page'] ?? null) ? $body['page'] : [];
        $page = ['module' => preg_replace('/[^a-z\-\/]/', '', (string) ($page['module'] ?? '')), 'record' => (int) ($page['record'] ?? 0)];

        try {
            $reply = AI::complete(AiContext::system($page), $messages, 2000);
        } catch (RuntimeException $e) {
            json_out(['ok' => false, 'error' => $e->getMessage()], 502);
        }
        RateLimit::recordAi((int) $u['id'], 'chat');
        json_out(['ok' => true, 'reply' => $reply !== '' ? $reply : 'I could not produce an answer. Try rephrasing the question.']);
    }
}
