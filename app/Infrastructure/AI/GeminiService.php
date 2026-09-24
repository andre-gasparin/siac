<?php

namespace App\Infrastructure\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;

    protected string $model;

    public function __construct()
    {
        $this->apiKey = (string) config('services.gemini.api_key', '');
        $this->model = (string) config('services.gemini.model', 'gemini-3.1-flash-lite');
    }

    /**
     * Check if Gemini API key is configured.
     */
    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Send prompt to Gemini API and return raw text response.
     *
     * @param  array<string, mixed>  $options
     */
    public function generateContent(string $prompt, array $options = []): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";
        $timeout = $options['timeout'] ?? 25;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
        ];

        if (isset($options['response_mime_type'])) {
            $payload['generationConfig'] = [
                'responseMimeType' => $options['response_mime_type'],
            ];
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout($timeout)
                ->retry([250, 750], throw: false)
                ->post($url, $payload);

            if (! $response->successful()) {
                Log::warning("Gemini API HTTP Error {$response->status()}: {$response->body()}");

                return null;
            }

            $data = $response->json();

            return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('Gemini API Exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Send prompt to Gemini API with JSON output mode and return parsed array.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>|null
     */
    public function generateJson(string $prompt, array $options = []): ?array
    {
        $options['response_mime_type'] = 'application/json';
        $rawText = $this->generateContent($prompt, $options);

        if (empty($rawText)) {
            return null;
        }

        $parsed = json_decode($rawText, true);

        return is_array($parsed) ? $parsed : null;
    }
}
