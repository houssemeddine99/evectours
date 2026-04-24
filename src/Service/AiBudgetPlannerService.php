<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class AiBudgetPlannerService
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

    /**
     * @param array $voyages  normalized voyage arrays
     * @param array $offers   normalized active offer arrays
     * @return array{recommendations: array, explanation: string}|null
     */
    public function plan(string $userInput, array $voyages, array $offers): ?array
    {
        if (!$this->enabled || $this->apiKey === '') {
            return null;
        }

        $voyageIds = implode('|', array_column(array_slice($voyages, 0, 20), 'id'));
        $offerIds  = implode('|', array_column($offers, 'id'));
        $cacheKey  = 'ai_budget_' . md5($userInput . $voyageIds . $offerIds);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($userInput, $voyages, $offers): ?array {
            $item->expiresAfter(3600); // 1 hour

            $voyageList = implode("\n", array_map(fn($v) => sprintf(
                'ID %d: %s → %s | %s TND/person | %s to %s',
                $v['id'], $v['title'], $v['destination'],
                number_format((float)($v['price'] ?? 0), 0),
                $v['start_date'] ?? 'TBD', $v['end_date'] ?? 'TBD'
            ), array_slice($voyages, 0, 20)));

            $offerList = empty($offers) ? 'No active offers.' : implode("\n", array_map(fn($o) => sprintf(
                'Offer ID %d: %s — %s%% off voyage "%s" (valid until %s)',
                $o['id'], $o['title'], $o['discount_percentage'], $o['voyage_title'], $o['end_date'] ?? 'TBD'
            ), $offers));

            $payload = [
                'model'    => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a smart travel planner. A user describes their travel budget and preferences. You recommend the best 1-3 matching voyages from the list, apply available offers if they fit, and calculate the estimated total. Reply in this JSON structure: {"recommendations": [{"voyage_id": X, "offer_id": null_or_Y, "estimated_price": Z, "reason": "..."}], "explanation": "..."}. Only use voyage IDs and offer IDs from the provided lists. No other text outside the JSON.'],
                    ['role' => 'user', 'content' => "User request: {$userInput}\n\nAvailable voyages:\n{$voyageList}\n\nActive offers:\n{$offerList}"],
                ],
                'temperature' => 0.5,
                'max_tokens'  => 600,
            ];

            $body = json_encode($payload);
            if (!is_string($body)) {
                return null;
            }

            [$raw, $code] = $this->sendRequest($body);
            if (!is_string($raw) || $code >= 400) {
                $this->logger->warning('AiBudgetPlannerService: API failed', ['code' => $code]);
                return null;
            }

            $decoded = json_decode($raw, true);
            $content = $decoded['choices'][0]['message']['content'] ?? '';
            if (!is_string($content) || $content === '') {
                return null;
            }

            preg_match('/\{.*\}/s', $content, $m);
            if (empty($m[0])) {
                return null;
            }

            $result = json_decode($m[0], true);
            return is_array($result) ? $result : null;
        });
    }

    /** @return array{0: ?string, 1: int} */
    private function sendRequest(string $body): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($this->apiUrl);
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $this->apiKey, 'Content-Type: application/json'],
                    CURLOPT_POSTFIELDS     => $body,
                    CURLOPT_TIMEOUT        => 20,
                ]);
                $raw  = curl_exec($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                return [is_string($raw) ? $raw : null, $code];
            }
        }
        $ctx = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => ['Authorization: Bearer ' . $this->apiKey, 'Content-Type: application/json'],
            'content' => $body, 'timeout' => 20, 'ignore_errors' => true,
        ]]);
        $raw     = @file_get_contents($this->apiUrl, false, $ctx);
        $headers = $http_response_header ?? [];
        preg_match('/\s(\d{3})\s/', (string) ($headers[0] ?? ''), $match);
        return [is_string($raw) ? $raw : null, isset($match[1]) ? (int) $match[1] : 0];
    }
}
