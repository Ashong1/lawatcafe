<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    protected $apiKey;
    protected $model;

    public function __construct()
    {
        $this->apiKey = env('OPENROUTER_API_KEY');
        $this->model = env('OPENROUTER_MODEL', 'google/gemini-2.5-flash'); 
    }

    /**
     * Handle chat interactions for the Captive Portal AI Assistant.
     */
    public function chat($message, $history = [])
    {
        if (empty($this->apiKey)) {
            return "AI service is currently offline (API key missing).";
        }

        $messages = [
            [
                'role' => 'system',
                'content' => "You are Lawa-Bot, a helpful AI assistant for Lawa't Cafe. Your job is to help users with Wi-Fi connections, vouchers, and general cafe info. Be very concise, polite, and brief (under 3 sentences). If they have 'Connected without internet' issues, tell them to wait 10 seconds or manually navigate to http://neverssl.com. If they ask for Wi-Fi prices, tell them it starts at PHP 20 for 1 hour."
            ]
        ];

        // Append history (if provided)
        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content']
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $message
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'HTTP-Referer' => config('app.url'),
                'X-Title' => config('app.name'),
            ])->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => $this->model,
                'messages' => $messages,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['choices'][0]['message']['content'] ?? 'I could not process that request right now.';
            }

            Log::error('OpenRouter Chat Error: ' . $response->body());
            return 'Sorry, my neural network is currently down for maintenance.';
        } catch (\Exception $e) {
            Log::error('OpenRouter Chat Exception: ' . $e->getMessage());
            return 'An error occurred while connecting to my brain.';
        }
    }

    /**
     * Extract payment details from unstructured text (emails or OCR text).
     */
    public function extractPaymentDetails($text)
    {
        if (empty($this->apiKey)) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'HTTP-Referer' => config('app.url'),
                'X-Title' => config('app.name'),
            ])->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => 'google/gemini-2.5-flash', // Fast reasoning model
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "You are an expert data extractor. Extract the e-wallet (GCash, Maya, Bank) 'Reference Number' and the 'Amount' paid from the provided text. Return ONLY a valid JSON object with keys 'reference_number' (string) and 'amount' (float). Do not include markdown (like ```json), HTML, or any other text. If not found, return empty strings."
                    ],
                    [
                        'role' => 'user',
                        'content' => $text
                    ]
                ],
            ]);

            if ($response->successful()) {
                $content = $response->json()['choices'][0]['message']['content'];
                // Clean up possible markdown if the model disobeys
                $content = str_replace(['```json', '```'], '', trim($content));
                $data = json_decode(trim($content), true);
                
                if (isset($data['reference_number']) && isset($data['amount']) && !empty($data['reference_number'])) {
                    return $data;
                }
            }
            return null;
        } catch (\Exception $e) {
            Log::error('OpenRouter Extractor Exception: ' . $e->getMessage());
            return null;
        }
    }
}
