<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Configuration - keys loaded from .env
$_env = parse_ini_file(dirname(__DIR__) . '/.env') ?: [];
define('OPENAI_API_KEY',    $_env['OPENAI_API_KEY']    ?? '');
define('ANTHROPIC_API_KEY', $_env['ANTHROPIC_API_KEY'] ?? '');
define('GOOGLE_API_KEY',    $_env['GOOGLE_API_KEY']    ?? '');


// Rate limiting configuration
define('MAX_REQUESTS_PER_MINUTE', 20);
define('MAX_MESSAGE_LENGTH', 4000);

class AIAssistant {
    private $provider;
    private $message;
    private $history;

    public function __construct($provider, $message, $history = []) {
        $this->provider = $provider;
        $this->message  = $message;
        $this->history  = $history;
    }

    public function getResponse() {
        if (strlen($this->message) > MAX_MESSAGE_LENGTH) {
            return [
                'success' => false,
                'error'   => 'Message too long. Maximum length is ' . MAX_MESSAGE_LENGTH . ' characters.'
            ];
        }

        switch ($this->provider) {
            case 'openai':  return $this->callOpenAI();
            case 'claude':  return $this->callClaude();
            case 'gemini':  return $this->callGemini();
            case 'copilot': return $this->callCopilot();
            default:
                return ['success' => false, 'error' => 'Invalid AI provider selected.'];
        }
    }

    private function callOpenAI() {
        if (empty(OPENAI_API_KEY)) {
            return ['success' => false, 'error' => 'OpenAI API key not configured.'];
        }

        $data = [
            'model'       => 'gpt-4o-mini',
            'messages'    => $this->formatMessagesForOpenAI(),
            'temperature' => 0.7,
            'max_tokens'  => 1000
        ];

        $result = $this->httpPost(
            'https://api.openai.com/v1/chat/completions',
            $data,
            ['Authorization: Bearer ' . OPENAI_API_KEY]
        );

        if ($result['code'] !== 200) {
            $err = json_decode($result['body'], true);
            return ['success' => false, 'error' => $err['error']['message'] ?? 'OpenAI API error'];
        }

        $body = json_decode($result['body'], true);
        return [
            'success'  => true,
            'response' => $body['choices'][0]['message']['content'] ?? 'No response',
            'provider' => 'openai',
            'model'    => 'gpt-4o-mini'
        ];
    }

    private function callClaude() {
        if (empty(ANTHROPIC_API_KEY)) {
            return ['success' => false, 'error' => 'Anthropic API key not configured.'];
        }

        $data = [
            'model'      => 'claude-sonnet-4-20250514',
            'max_tokens' => 1024,
            'messages'   => $this->formatMessagesForClaude()
        ];

        $result = $this->httpPost(
            'https://api.anthropic.com/v1/messages',
            $data,
            [
                'x-api-key: ' . ANTHROPIC_API_KEY,
                'anthropic-version: 2023-06-01'
            ]
        );

        if ($result['code'] !== 200) {
            $err = json_decode($result['body'], true);
            return ['success' => false, 'error' => $err['error']['message'] ?? 'Claude API error'];
        }

        $body = json_decode($result['body'], true);
        return [
            'success'  => true,
            'response' => $body['content'][0]['text'] ?? 'No response',
            'provider' => 'claude',
            'model'    => 'claude-sonnet-4-20250514'
        ];
    }

    private function callGemini() {
        if (empty(GOOGLE_API_KEY)) {
            return ['success' => false, 'error' => 'Google API key not configured. Get one free at https://aistudio.google.com/app/apikey'];
        }

        // Current stable models — source: https://ai.google.dev/gemini-api/docs/models
        // All use v1beta endpoint (v1 does NOT support these models)
        $models = [
            'gemini-2.5-flash',      // Stable — best price/performance
            'gemini-2.5-flash-lite', // Stable — fastest & cheapest
            'gemini-2.0-flash',      // Deprecated Mar 31 2026, still works
            'gemini-2.0-flash-lite', // Deprecated Mar 31 2026, still works
        ];

        $data = [
            'contents'         => $this->formatMessagesForGemini(),
            'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 1000]
        ];

        $lastResult = null;
        foreach ($models as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . GOOGLE_API_KEY;
            $lastResult = $this->httpPost($url, $data);

            if ($lastResult['code'] === 200) {
                $body = json_decode($lastResult['body'], true);
                return [
                    'success'  => true,
                    'response' => $body['candidates'][0]['content']['parts'][0]['text'] ?? 'No response',
                    'provider' => 'gemini',
                    'model'    => $model
                ];
            }
        }

        $err = json_decode($lastResult['body'], true);
        return ['success' => false, 'error' => $err['error']['message'] ?? 'Gemini API error.'];
    }

    private function callCopilot() {
        return [
            'success' => false,
            'error'   => 'Microsoft Copilot requires Azure OpenAI Service. Please use ChatGPT, Claude, or Gemini instead.'
        ];
    }

    private function formatMessagesForOpenAI() {
        $messages = [['role' => 'system', 'content' => 'You are a helpful AI assistant. Provide clear, concise, and accurate responses.']];
        foreach (array_slice($this->history, -10) as $msg) {
            $messages[] = [
                'role'    => $msg['role'] === 'user' ? 'user' : 'assistant',
                'content' => $msg['content']
            ];
        }
        return $messages;
    }

    private function formatMessagesForClaude() {
        $messages = [];
        foreach (array_slice($this->history, -10) as $msg) {
            $messages[] = [
                'role'    => $msg['role'] === 'user' ? 'user' : 'assistant',
                'content' => $msg['content']
            ];
        }
        return $messages;
    }

    private function formatMessagesForGemini() {
        $contents = [];
        foreach (array_slice($this->history, -10) as $msg) {
            $contents[] = [
                'role'  => $msg['role'] === 'user' ? 'user' : 'model',
                'parts' => [['text' => $msg['content']]]
            ];
        }
        return $contents;
    }

    private function httpPost($url, $data, $extraHeaders = []) {
        $headers = array_merge(['Content-Type: application/json'], $extraHeaders);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'body' => $body];
    }
}

// Rate limiting
function checkRateLimit() {
    session_start();
    $now = time();
    if (!isset($_SESSION['request_timestamps'])) {
        $_SESSION['request_timestamps'] = [];
    }
    $_SESSION['request_timestamps'] = array_values(array_filter(
        $_SESSION['request_timestamps'],
        function($t) use ($now) { return ($now - $t) < 60; }
    ));
    if (count($_SESSION['request_timestamps']) >= MAX_REQUESTS_PER_MINUTE) {
        return false;
    }
    $_SESSION['request_timestamps'][] = $now;
    return true;
}

// Main handler
try {
    if (!checkRateLimit()) {
        echo json_encode(['success' => false, 'error' => 'Rate limit exceeded. Please wait a moment.']);
        exit;
    }

    $input = file_get_contents('php://input');
    $data  = json_decode($input, true);

    if (!$data || !isset($data['provider'], $data['message'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid request format.']);
        exit;
    }

    $provider = htmlspecialchars(strip_tags($data['provider']));
    $message  = trim($data['message']);
    $history  = $data['history'] ?? [];

    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Message cannot be empty.']);
        exit;
    }

    $assistant = new AIAssistant($provider, $message, $history);
    echo json_encode($assistant->getResponse());

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>