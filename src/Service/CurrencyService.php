<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CurrencyService
{
    // Base currency stored in DB
    private const BASE_CURRENCY = 'TND';

    // Known currency symbols
    private const SYMBOLS = [
        'TND' => 'TND',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'JPY' => '¥',
        'CAD' => 'CA$',
        'AUD' => 'A$',
        'CHF' => 'CHF',
        'CNY' => '¥',
        'SAR' => 'SAR',
        'AED' => 'AED',
        'MAD' => 'MAD',
        'DZD' => 'DZD',
        'EGP' => 'EGP',
        'LYD' => 'LYD',
    ];

    public function __construct(
        private RequestStack $requestStack,
        private CacheInterface $cache,
    ) {}

    public function getUserCurrency(): string
    {
        $session = $this->requestStack->getSession();

        // Allow manual override stored in session
        $override = $session->get('currency_override');
        if ($override) {
            return $override;
        }

        $detected = $session->get('detected_currency');
        if ($detected) {
            return $detected;
        }

        return self::BASE_CURRENCY;
    }

    public function setOverride(string $currency): void
    {
        $this->requestStack->getSession()->set('currency_override', strtoupper($currency));
    }

    public function getSymbol(string $currency): string
    {
        return self::SYMBOLS[$currency] ?? $currency;
    }

    public function convert(float $amount, string $toCurrency): float
    {
        if ($toCurrency === self::BASE_CURRENCY) {
            return $amount;
        }

        $rates = $this->getExchangeRates();

        // rates are relative to EUR; convert TND -> EUR -> target
        $tndToEur = isset($rates['TND']) ? 1 / $rates['TND'] : null;
        $eurToTarget = $rates[$toCurrency] ?? null;

        if ($tndToEur === null || $eurToTarget === null) {
            return $amount;
        }

        return $amount * $tndToEur * $eurToTarget;
    }

    /**
     * Convert an amount between any two currencies (e.g. USD from an external API -> TND).
     */
    public function convertCurrency(float $amount, string $from, string $to): float
    {
        $from = strtoupper($from);
        $to   = strtoupper($to);
        if ($from === $to) {
            return $amount;
        }

        $rates = $this->getExchangeRates(); // EUR-based: value = units per 1 EUR
        $fromRate = $from === 'EUR' ? 1.0 : ($rates[$from] ?? null);
        $toRate   = $to === 'EUR' ? 1.0 : ($rates[$to] ?? null);

        if ($fromRate === null || $toRate === null || (float) $fromRate === 0.0) {
            return $amount;
        }

        return $amount / $fromRate * $toRate;
    }

    /**
     * Convert from a source currency to the target (defaults to the user's currency) and format it.
     */
    public function formatFrom(float $amount, string $from, ?string $to = null): string
    {
        $to = $to ?? $this->getUserCurrency();
        return $this->format($this->convertCurrency($amount, $from, $to), $to);
    }

    public function format(float $amount, string $currency): string
    {
        $symbol = $this->getSymbol($currency);
        // Currencies with no decimals
        $noDecimals = ['JPY', 'DZD'];
        $decimals = in_array($currency, $noDecimals) ? 0 : 2;

        return $symbol . ' ' . number_format($amount, $decimals, '.', ',');
    }

    private function detectFromIp(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return self::BASE_CURRENCY;
        }

        $ip = $request->getClientIp();

        // Local/private IP — default to base currency
        if (!$ip || $ip === '127.0.0.1' || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
            return self::BASE_CURRENCY;
        }

        try {
            $cacheKey = 'ip_currency_' . md5($ip);
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($ip): string {
                $item->expiresAfter(3600 * 24); // 24h cache per IP

                $ctx = stream_context_create(['http' => ['timeout' => 3]]);
                $json = @file_get_contents("http://ip-api.com/json/{$ip}?fields=currency", false, $ctx);

                if ($json) {
                    $data = json_decode($json, true);
                    if (!empty($data['currency'])) {
                        return strtoupper($data['currency']);
                    }
                }

                return self::BASE_CURRENCY;
            });
        } catch (\Throwable) {
            return self::BASE_CURRENCY;
        }
    }

    /** @return array<mixed> */
    private function getExchangeRates(): array
    {
        // v2 key: previous cache may hold live rates that lack TND/DZD/etc.
        return $this->cache->get('exchange_rates_v2', function (ItemInterface $item): array {
            $item->expiresAfter(3600 * 6); // refresh every 6h

            // EUR-based fallback. Also fills currencies the live ECB feed (Frankfurter)
            // does NOT provide — e.g. TND, DZD, MAD, EGP, SAR, AED, LYD — so conversions
            // to those still work instead of silently returning the unconverted amount.
            $fallback = [
                'EUR' => 1.0,
                'USD' => 1.09,
                'GBP' => 0.86,
                'TND' => 3.37,
                'MAD' => 10.9,
                'DZD' => 147.0,
                'EGP' => 53.0,
                'SAR' => 4.09,
                'AED' => 4.00,
                'LYD' => 5.30,
                'JPY' => 163.0,
                'CAD' => 1.49,
                'AUD' => 1.67,
                'CHF' => 0.97,
            ];

            $ctx = stream_context_create(['http' => ['timeout' => 5]]);
            $json = @file_get_contents('https://api.frankfurter.app/latest?base=EUR', false, $ctx);

            if ($json) {
                $data = json_decode($json, true);
                if (!empty($data['rates'])) {
                    $rates = $data['rates'];
                    $rates['EUR'] = 1.0;
                    // Live ECB rates win for majors; fallback fills the gaps (TND, DZD, …).
                    return array_merge($fallback, $rates);
                }
            }

            return $fallback;
        });
    }
}
