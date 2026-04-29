<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class AiVoyageService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $apiUrl,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly bool $enabled,
        #[Target('cache.ai_responses')]
        private readonly CacheInterface $cache,
    ) {}

    public function generateDescription(string $title, string $destination, int $durationDays, ?string $existing = null): ?string
    {
        if (!$this->enabled || $this->apiKey === '') {
            return null;
        }

        return $this->cache->get('ai_desc_' . md5($title . $destination . $durationDays), function (ItemInterface $item) use ($title, $destination, $durationDays, $existing): ?string {
            $item->expiresAfter(172800);

            $context = $existing ? "Improve this existing description:\n{$existing}\n\n" : '';
            $payload = [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a travel copywriter. Write vivid, engaging voyage descriptions. Keep it under 150 words. No bullet points, plain prose only.'],
                    ['role' => 'user', 'content' => "{$context}Write a description for a {$durationDays}-day voyage titled \"{$title}\" to {$destination}."],
                ],
                'temperature' => 0.7,
                'max_tokens' => 250,
            ];

            return $this->callApi($payload);
        });
    }

    public function generateItinerary(string $title, string $destination, int $durationDays): ?string
    {
        if (!$this->enabled || $this->apiKey === '') {
            return null;
        }

        return $this->cache->get('ai_itin_' . md5($title . $destination . $durationDays), function (ItemInterface $item) use ($title, $destination, $durationDays): ?string {
            $item->expiresAfter(172800);

            $payload = [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a travel expert. Create a concise day-by-day itinerary. Each day: one line starting with "Day N:". Keep total under 300 words.'],
                    ['role' => 'user', 'content' => "Create a {$durationDays}-day itinerary for a trip to {$destination} titled \"{$title}\"."],
                ],
                'temperature' => 0.6,
                'max_tokens' => 400,
            ];

            return $this->callApi($payload);
        });
    }

    private function callApi(array $payload): ?string
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!is_string($body)) {
            return null;
        }

        [$raw, $httpCode, $error] = $this->sendRequest($body);

        if (!is_string($raw) || $httpCode >= 400) {
            $this->logger->warning('AiVoyageService request failed', ['code' => $httpCode, 'error' => $error]);
            return null;
        }

        $decoded = json_decode($raw, true);
        $content = $decoded['choices'][0]['message']['content'] ?? null;

        return is_string($content) && trim($content) !== '' ? trim($content) : null;
    }

    /** @return array{0: ?string, 1: int, 2: ?string} */
    private function sendRequest(string $body): array
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
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                $raw = curl_exec($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err = curl_error($ch);
                curl_close($ch);
                return [is_string($raw) ? $raw : null, $code, $err !== '' ? $err : null];
            }
        }

        $ctx = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => ['Authorization: Bearer ' . $this->apiKey, 'Content-Type: application/json'],
            'content' => $body,
            'timeout' => 15,
            'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents($this->apiUrl, false, $ctx);
        $headers = $http_response_header;
        preg_match('/\s(\d{3})\s/', (string) ($headers[0] ?? ''), $m);
        return [is_string($raw) ? $raw : null, isset($m[1]) ? (int) $m[1] : 0, $raw === false ? 'failed' : null];
    }
}
