<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class StripeRefundService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $secretKey,
    ) {
    }

    /**
     * @return array{success: bool, refundId?: string, error?: string}
     */
    public function createRefund(string $paymentReference, string $amount): array
    {
        if (trim($this->secretKey) === '') {
            return ['success' => false, 'error' => 'Stripe is not configured. Missing STRIPE_SECRET_KEY.'];
        }

        $reference = trim($paymentReference);
        if ($reference === '') {
            return ['success' => false, 'error' => 'Payment reference is required (pi_... or ch_...).'];
        }

        $amountCents = (int) round((float) $amount * 100);
        if ($amountCents <= 0) {
            return ['success' => false, 'error' => 'Invalid refund amount.'];
        }

        try {
            $params = ['amount' => (string) $amountCents];
            if (str_starts_with($reference, 'ch_')) {
                $params['charge'] = $reference;
            } else {
                $params['payment_intent'] = $reference;
            }

            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Authorization: Bearer {$this->secretKey}\r\n"
                        . "Content-Type: application/x-www-form-urlencoded\r\n"
                        . "User-Agent: travagir-php-refund\r\n",
                    'content' => http_build_query($params),
                    'timeout' => 15,
                    'ignore_errors' => true,
                ],
            ]);

            $raw = @file_get_contents('https://api.stripe.com/v1/refunds', false, $context);
            $headers = $http_response_header;
            $statusLine = isset($headers[0]) ? (string) $headers[0] : '';
            preg_match('/\s(\d{3})\s/', $statusLine, $matches);
            $httpCode = isset($matches[1]) ? (int) $matches[1] : 0;

            if (!is_string($raw)) {
                return ['success' => false, 'error' => 'Failed to connect to Stripe API.'];
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return ['success' => false, 'error' => 'Invalid response from Stripe API.'];
            }

            if ($httpCode >= 400 || isset($decoded['error'])) {
                $message = is_array($decoded['error'] ?? null) ? (string) ($decoded['error']['message'] ?? 'Stripe refund failed.') : 'Stripe refund failed.';
                $this->logger->error('Stripe refund failed', ['error' => $message, 'reference' => $reference, 'http_code' => $httpCode]);
                return ['success' => false, 'error' => $message];
            }

            $refundId = isset($decoded['id']) ? (string) $decoded['id'] : null;
            if ($refundId === null || $refundId === '') {
                return ['success' => false, 'error' => 'Stripe refund ID missing in response.'];
            }

            return ['success' => true, 'refundId' => $refundId];
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected Stripe refund error', ['error' => $e->getMessage(), 'reference' => $reference]);
            return ['success' => false, 'error' => 'Unexpected refund error.'];
        }
    }
}