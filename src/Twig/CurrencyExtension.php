<?php

namespace App\Twig;

use App\Service\CurrencyService;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;

class CurrencyExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private CurrencyService $currencyService) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter('format_price', [$this, 'formatPrice']),
        ];
    }

    public function getGlobals(): array
    {
        try {
            $currency = $this->currencyService->getUserCurrency();
        } catch (\Throwable) {
            $currency = 'TND';
        }
        return [
            'user_currency'        => $currency,
            'user_currency_symbol' => $this->currencyService->getSymbol($currency),
        ];
    }

    public function formatPrice(mixed $amount, ?string $currency = null): string
    {
        if ($amount === null || $amount === '' || $amount === false) {
            return 'Price on request';
        }

        $amount = (float) $amount;
        $currency ??= $this->currencyService->getUserCurrency();
        $converted = $this->currencyService->convert($amount, $currency);

        return $this->currencyService->format($converted, $currency);
    }
}
