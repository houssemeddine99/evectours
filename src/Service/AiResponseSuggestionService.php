<?php

namespace App\Service;

use App\Entity\Reclamation;
use Psr\Log\LoggerInterface;

class AiResponseSuggestionService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $apiUrl,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly bool $enabled,
    ) {
    }

    public function suggestForReclamation(Reclamation $reclamation): ?string
    {
        if (!$this->enabled || $this->apiKey === '') {
            return null;
        }

        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a customer support assistant. Write a concise, polite admin response for a complaint. Do not invent facts. Keep it under 120 words.',
                ],
                [
                    'role' => 'user',
                    'content' => sprintf(
                        "Reclamation title: %s\nDescription: %s\nPriority: %s\nStatus: %s\nProvide one ready-to-send admin response.",
                        $reclamation->getTitle(),
                        $reclamation->getDescription(),
                        $reclamation->getPriority(),
                        $reclamation->getStatus()
                    ),
                ],
            ],
            'temperature' => 0.4,
            'max_tokens' => 220,
        ];

        $requestBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!is_string($requestBody)) {
            return null;
        }

        [$raw, $httpCode, $error] = $this->sendHttpRequest($requestBody);

        if (!is_string($raw) || $httpCode >= 400) {
            $this->logger->warning('AI suggestion request failed', [
                'http_code' => $httpCode,
                'error' => $error,
            ]);
            return null;
        }

        $decoded = json_decode((string) $raw, true);
        $content = $decoded['choices'][0]['message']['content'] ?? null;

        if (!is_string($content) || trim($content) === '') {
            return null;
        }

        return trim($content);
    }

    /**
     * @return array{0: ?string, 1: int, 2: ?string}
     */
    private function sendHttpRequest(string $requestBody): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($this->apiUrl);
            if ($ch !== false) {
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/json',
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
                curl_setopt($ch, CURLOPT_TIMEOUT, 12);

                $raw = curl_exec($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                return [is_string($raw) ? $raw : null, $httpCode, $curlError !== '' ? $curlError : null];
            }
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/json',
                ],
                'content' => $requestBody,
                'timeout' => 12,
                'ignore_errors' => true,
            ],
        ]);

        $raw = @file_get_contents($this->apiUrl, false, $context);
        $headers = $http_response_header ?? [];
        $statusLine = is_array($headers) && isset($headers[0]) ? (string) $headers[0] : '';
        preg_match('/\s(\d{3})\s/', $statusLine, $matches);
        $httpCode = isset($matches[1]) ? (int) $matches[1] : 0;

        return [is_string($raw) ? $raw : null, $httpCode, $raw === false ? 'HTTP request failed' : null];
    }
}