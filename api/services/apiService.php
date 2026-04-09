<?php

/**
 * ApiService - Handles external AI provider calls (Gemini and OpenAI).
 * Layer 2 & 3 of the Chatbot Fallback System.
 */
class ApiService
{
    private $geminiKey;
    private $openaiKey;
    private $systemInstruction;

    public function __construct($geminiKey, $openaiKey, $systemInstruction)
    {
        // Fallback to global ENV/SERVER arrays if keys aren't passed directly or are empty
        $this->geminiKey = $geminiKey ?: ($_ENV['GEMINI_API_KEY'] ?? $_SERVER['GEMINI_API_KEY'] ?? '');
        $this->openaiKey = $openaiKey ?: ($_ENV['OPENAI_API_KEY'] ?? $_SERVER['OPENAI_API_KEY'] ?? '');
        $this->systemInstruction = $systemInstruction;

        if (empty($this->geminiKey)) {
            error_log("ApiService Warning: GEMINI_API_KEY is not set.");
        }
    }

    /**
     * Call Gemini API
     */
    public function callGemini($model, $message, $history)
    {
        if (empty($this->geminiKey)) return false;

        $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->geminiKey}";

        $contents = [];
        foreach ($history as $m) {
            $contents[] = [
                'role' => ($m['role'] === 'user' ? 'user' : 'model'),
                'parts' => [['text' => $m['text']]]
            ];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

        $body = [
            'system_instruction' => ['parts' => [['text' => $this->systemInstruction]]],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1024
            ]
        ];

        return $this->executeCurl($apiUrl, $body, 'gemini');
    }

    /**
     * Call OpenAI API
     */
    public function callOpenAI($model, $message, $history)
    {
        if (empty($this->openaiKey)) return false;

        $apiUrl = 'https://api.openai.com/v1/chat/completions';

        $messages = [['role' => 'system', 'content' => $this->systemInstruction]];
        foreach ($history as $m) {
            $messages[] = [
                'role' => ($m['role'] === 'user' ? 'user' : 'assistant'),
                'content' => $m['text']
            ];
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        $body = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 1024
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->openaiKey
        ];

        return $this->executeCurl($apiUrl, $body, 'openai', $headers);
    }

    /**
     * Execute cURL request
     */
    private function executeCurl($url, $body, $provider, $headers = ['Content-Type: application/json'])
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("ApiService Error ({$provider}): HTTP {$httpCode} - " . $response . ($error ? " - Curl Error: {$error}" : ""));
            return false;
        }

        $data = json_decode($response, true);
        
        if ($provider === 'gemini') {
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? false;
        } else {
            return $data['choices'][0]['message']['content'] ?? false;
        }
    }
}
