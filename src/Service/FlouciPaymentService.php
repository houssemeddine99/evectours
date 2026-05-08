<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class FlouciPaymentService
{
    private const BASE = 'https://developers.flouci.com/api/v2';

    public function __construct(
        private readonly string $appPublic,
        private readonly string $appSecret,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Returns ['payment_id' => '...', 'link' => 'https://...'] or null on error.
     * Amount is in TND; Flouci receives it as millimes (1 TND = 1000 millimes).
     * @return array<mixed>
     */
    public function createPayment(
        int    $reservationId,
        float  $amountTnd,
        string $successUrl,
        string $failUrl,
    ): ?array {
        if ($this->appPublic === '' || $this->appSecret === '') {
            $this->logger->error('FlouciPaymentService: credentials not configured');
            return null;
        }

        $millimes = (int) round($amountTnd * 1000);
        $millimes = max(100, $millimes); // Flouci minimum

        $encodedBody = json_encode([
            'amount'                => $millimes,
            'success_link'          => $successUrl,
            'fail_link'             => $failUrl,
            'developer_tracking_id' => 'reservation_' . $reservationId,
            'accept_card'           => true,
        ]);
        $body = $encodedBody !== false ? $encodedBody : null;

        $raw = $this->request('POST', self::BASE . '/generate_payment', $body);
        if ($raw === null) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!($data['result']['success'] ?? false)) {
            $this->logger->error('Flouci createPayment failed', ['raw' => substr($raw, 0, 300)]);
            return null;
        }

        return [
            'payment_id' => (string) ($data['result']['payment_id'] ?? ''),
            'link'       => (string) ($data['result']['link'] ?? ''),
        ];
    }

    /**
     * Returns 'SUCCESS', 'PENDING', 'EXPIRED', 'FAILURE', or null on API error.
     */
    public function verifyPayment(string $paymentId): ?string
    {
        $raw = $this->request('GET', self::BASE . '/verify_payment/' . urlencode($paymentId), null);
        if ($raw === null) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!($data['success'] ?? false)) {
            $this->logger->warning('Flouci verifyPayment failed', ['payment_id' => $paymentId, 'raw' => substr($raw, 0, 300)]);
            return null;
        }

        return $data['result']['status'] ?? null;
    }

    private function request(string $method, string $url, ?string $jsonBody): ?string
    {
        $auth    = $this->appPublic . ':' . $this->appSecret;
        $headers = [
            'Authorization: Bearer ' . $auth,
            'Content-Type: application/json',
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return null;
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            if ($method !== '') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            if ($jsonBody !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
            }
            $result = curl_exec($ch);
            curl_close($ch);
            return is_string($result) ? $result : null;
        }

        $opts = [
            'method'        => $method,
            'header'        => implode("\r\n", $headers),
            'timeout'       => 15,
            'ignore_errors' => true,
        ];
        if ($jsonBody !== null) {
            $opts['content'] = $jsonBody;
        }
        $raw = @file_get_contents($url, false, stream_context_create(['http' => $opts]));
        return is_string($raw) ? $raw : null;
    }
}
