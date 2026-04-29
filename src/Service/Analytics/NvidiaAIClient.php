<?php
namespace App\Service\Analytics;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class NvidiaAIClient
{
    private HttpClientInterface $httpClient;
    private string $apiKeyNvidia;

    public function __construct(string $apiKeyNvidia)
    {
        $this->apiKeyNvidia = $apiKeyNvidia;
        $this->httpClient = \Symfony\Component\HttpClient\HttpClient::create();
    }

// src/Service/Analytics/NvidiaAIClient.php

// src/Service/Analytics/NvidiaAIClient.php
public function chat(array $messages, array $tools = []): array 
{
    $body = [
        'model' => 'meta/llama-3.1-8b-instruct', // Ensure you use an 'instruct' model
        'messages' => $messages,
        'temperature' => 0.2,
    ];

    if (!empty($tools)) {
        $body['tools'] = $tools;
    }

    $response = $this->httpClient->request('POST', 'https://integrate.api.nvidia.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $this->apiKeyNvidia,
            'Content-Type' => 'application/json',
        ],
        'json' => $body
    ]);

    return $response->toArray();
}
}