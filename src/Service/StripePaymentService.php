<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class StripePaymentService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $secretKey,
    ) {}

    /**
     * Create and immediately confirm a Stripe test PaymentIntent using pm_card_visa.
     * @return array{success: bool, reference?: string, error?: string}
     */
    public function createAndConfirmTestPayment(string $amount): array
    {
        if (trim($this->secretKey) === '') {
            return ['success' => false, 'error' => 'STRIPE_SECRET_KEY is not set in .env'];
        }

        $amountCents = (int) round((float) $amount * 100);
        if ($amountCents <= 0) {
            $amountCents = 100; // fallback: €1.00 for test
        }

        // Build params manually — avoids http_build_query bracket-encoding issues
        $params = 'amount=' . $amountCents
            . '&currency=eur'
            . '&payment_method=pm_card_visa'
            . '&payment_method_types[]=card'
            . '&confirm=true';

        $raw = $this->post('https://api.stripe.com/v1/payment_intents', $params);

        if ($raw === null) {
            return ['success' => false, 'error' => 'Could not reach Stripe API (check allow_url_fopen / curl).'];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'error' => 'Stripe returned invalid JSON: ' . substr($raw, 0, 120)];
        }

        if (isset($decoded['error'])) {
            $msg = is_array($decoded['error'])
                ? ($decoded['error']['message'] ?? 'Stripe error')
                : 'Stripe error';
            $this->logger->error('StripePaymentService error', ['error' => $msg]);
            return ['success' => false, 'error' => $msg];
        }

        $id = $decoded['id'] ?? null;
        if (!is_string($id) || !str_starts_with($id, 'pi_')) {
            return ['success' => false, 'error' => 'Unexpected Stripe response: ' . substr($raw, 0, 120)];
        }

        $this->logger->info('StripePaymentService: test payment created', ['pi' => $id]);
        return ['success' => true, 'reference' => $id];
    }

    private function post(string $url, string $body): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $body,
                    CURLOPT_HTTPHEADER     => [
                        'Authorization: Bearer ' . $this->secretKey,
                        'Content-Type: application/x-www-form-urlencoded',
                    ],
                    CURLOPT_TIMEOUT        => 15,
                ]);
                $result = curl_exec($ch);
                curl_close($ch);
                return is_string($result) ? $result : null;
            }
        }

        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => 'Authorization: Bearer ' . $this->secretKey . "\r\n"
                    . "Content-Type: application/x-www-form-urlencoded\r\n",
                'content'       => $body,
                'timeout'       => 15,
                'ignore_errors' => true,
            ],
        ]);
        $result = @file_get_contents($url, false, $context);
        return is_string($result) ? $result : null;
    }
}
