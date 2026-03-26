<?php
// src/AI.php
namespace App;

use GuzzleHttp\Client;

class AI {
    
    public static function generate($prompt, $system_prompt = "Eres un asistente útil.", $provider = 'openai') {
        $client = new Client();

        if ($provider === 'openai') {
            return self::callOpenAI($client, $prompt, $system_prompt);
        } else {
            return self::callGemini($client, $prompt, $system_prompt);
        }
    }

    private static function callOpenAI($client, $prompt, $system_prompt) {
        try {
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $_ENV['OPENAI_API_KEY'],
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'model' => 'gpt-4o',
                    'messages' => [
                        ['role' => 'system', 'content' => $system_prompt],
                        ['role' => 'user', 'content' => $prompt]
                    ]
                ]
            ]);
            $body = json_decode($response->getBody(), true);
            return $body['choices'][0]['message']['content'] ?? "Error en respuesta OpenAI";
        } catch (\Exception $e) {
            return "Error conectando con OpenAI: " . $e->getMessage();
        }
    }

    private static function callGemini($client, $prompt, $system_prompt) {
        // Gemini no usa System Prompt igual que GPT, lo concatenamos para simplificar
        $full_prompt = $system_prompt . "\n\nUsuario: " . $prompt;
        $key = $_ENV['GEMINI_API_KEY'];
        
        try {
            $response = $client->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key={$key}", [
                'json' => [
                    'contents' => [['parts' => [['text' => $full_prompt]]]]
                ]
            ]);
            $body = json_decode($response->getBody(), true);
            return $body['candidates'][0]['content']['parts'][0]['text'] ?? "Error en respuesta Gemini";
        } catch (\Exception $e) {
            return "Error conectando con Gemini: " . $e->getMessage();
        }
    }
}