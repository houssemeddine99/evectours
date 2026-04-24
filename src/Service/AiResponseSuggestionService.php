<?php

namespace App\Service;

use App\Entity\Reclamation;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class AiResponseSuggestionService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $apiUrl,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly bool $enabled,
        #[Target('cache.ai_responses')]
        private readonly CacheInterface $cache,
    ) {
    }

    public function suggestForReclamation(Reclamation $reclamation): ?string
    {
        if (!$this->enabled || $this->apiKey === '') {
            return null;
        }

        $cacheKey = 'ai_suggest_' . $reclamation->getId() . '_' . $reclamation->getStatus() . '_' . $reclamation->getPriority();

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($reclamation): ?string {
            $item->expiresAfter(86400); // 24 hours — same reclamation state = same suggestion

        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => <<<PROMPT
You are a senior customer support agent for Travagir, a travel agency.
Your job is to write a personalised, ready-to-send admin reply to a customer complaint.

Rules:
- Read the title and description carefully and address the SPECIFIC issue raised.
- Adapt your tone to the priority: URGENT/HIGH → more urgent, empathetic, and action-oriented. LOW/NORMAL → calm and reassuring.
- If the complaint is about a REFUND → acknowledge the cancellation, confirm the refund process is being initiated, give an estimated timeframe (3-5 business days).
- If the complaint is about a DELAY or SCHEDULE → apologise, explain general causes, offer an update timeline.
- If the complaint is about SERVICE QUALITY (hotel, guide, transport) → apologise specifically, mention an internal review will be conducted.
- If the complaint is about BOOKING/TECHNICAL issues → reassure, mention the technical team will look into it.
- If none of the above match, write a warm, specific reply that directly references the user's words.
- Start with "Dear valued customer," — never use placeholders like [Name].
- Do NOT invent specific dates, names, order numbers, or amounts not given to you.
- Keep it between 60 and 130 words. No bullet points. Plain paragraph only.
PROMPT,
                ],
                [
                    'role' => 'user',
                    'content' => sprintf(
                        "Title: %s\nDescription: %s\nPriority: %s\nStatus: %s",
                        $reclamation->getTitle(),
                        $reclamation->getDescription(),
                        $reclamation->getPriority(),
                        $reclamation->getStatus()
                    ),
                ],
            ],
            'temperature' => 0.7,
            'max_tokens' => 260,
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
        }); // end cache->get()
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