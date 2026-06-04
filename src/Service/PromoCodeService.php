<?php

declare(strict_types=1);

namespace App\Service;

class PromoCodeService
{
    /** @var array<string, array{discount: int|float, type: string, description: string}> */
    private const CODES = [
        'WELCOME10' => ['discount' => 10,  'type' => 'percent', 'description' => '10% off your first booking'],
        'SUMMER20'  => ['discount' => 20,  'type' => 'percent', 'description' => '20% summer discount'],
        'EVEC15'    => ['discount' => 15,  'type' => 'percent', 'description' => '15% exclusive discount'],
        'FLAT50'    => ['discount' => 50,  'type' => 'fixed',   'description' => '50 TND off'],
    ];

    /**
     * @return array{discount: int|float, type: string, description: string}|null
     */
    public function validate(string $code): ?array
    {
        $upper = strtoupper(trim($code));
        return self::CODES[$upper] ?? null;
    }

    public function apply(float $price, string $code): float
    {
        $promo = $this->validate($code);
        if ($promo === null) {
            return $price;
        }

        if ($promo['type'] === 'percent') {
            return max(0, $price * (1 - $promo['discount'] / 100));
        }

        return max(0, $price - (float) $promo['discount']);
    }

    public function getDiscountAmount(float $price, string $code): float
    {
        return round($price - $this->apply($price, $code), 2);
    }
}
