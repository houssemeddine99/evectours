<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class AiCancellationService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $apiUrl,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly bool $enabled,
    ) {}

    /**
     * Generates a persuasive warning when a user is about to cancel a confirmed reservation.
     * @param array $reservation with keys: voyage_title, destination, voyage_start, total_price, number_of_people
     */
    public function getWarning(array $reservation): ?string
    {
        if (!$this->enabled || $this->apiKey === '') {
            return null;
        }

        $voyageTitle  = $reservation['voyage_title'] ?? 'your trip';
        $destination  = $reservation['destination'] ?? 'your destination';
        $startDate    = $reservation['voyage_start'] ?? 'soon';
        $price        = number_format((float) ($reservation['total_price'] ?? 0), 2);
        $people       = $reservation['number_of_people'] ?? 1;

        $daysUntil = '';
        if (!empty($reservation['voyage_start'])) {
            try {
                $start    = new \DateTime($reservation['voyage_start']);
                $today    = new \DateTime('today');
                $diff     = (int) $today->diff($start)->days;
                $daysUntil = $diff > 0 ? "{$diff} days until departure" : 'trip date has passed';
            } catch (\Throwable) {}
        }

        $payload = [
            'model'    => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a friendly travel advisor. Write a short, warm, persuasive message (2-3 sentences max) to convince a traveller not to cancel their booking. Focus on what they would miss. No bullet points. End with one emoji.'],
                ['role' => 'user', 'content' => "The user is about to cancel: \"{$voyageTitle}\" to {$destination}. Booking: {$people} person(s), {$price} TND paid. {$daysUntil}. Write the warning."],
            ],
            'temperature' => 0.8,
            'max_tokens'  => 120,
        ];

        $body = json_encode($payload);
        if (!is_string($body)) {
            return null;
        }

        [$raw, $code] = $this->sendRequest($body);
        if (!is_string($raw) || $code >= 400) {
            return null;
        }

        $decoded = json_decode($raw, true);
        $content = $decoded['choices'][0]['message']['content'] ?? null;
        return is_string($content) && trim($content) !== '' ? trim($content) : null;
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
                    CURLOPT_TIMEOUT        => 12,
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
            'content' => $body, 'timeout' => 12, 'ignore_errors' => true,
        ]]);
        $raw     = @file_get_contents($this->apiUrl, false, $ctx);
        $headers = $http_response_header ?? [];
        preg_match('/\s(\d{3})\s/', (string) ($headers[0] ?? ''), $match);
        return [is_string($raw) ? $raw : null, isset($match[1]) ? (int) $match[1] : 0];
    }
}
