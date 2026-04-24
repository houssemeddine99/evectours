<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class WeatherService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $apiKey,
        #[Target('cache.api_external')]
        private readonly CacheInterface $cache,
    ) {}

    /**
     * Returns current weather for a city, cached for 30 minutes.
     * @return array{temp: float, description: string, icon: string, city: string}|null
     */
    public function getCurrentWeather(string $city): ?array
    {
        if ($this->apiKey === '' || $this->apiKey === 'your_openweathermap_api_key') {
            return null;
        }

        return $this->cache->get('weather_' . md5($city), function (ItemInterface $item) use ($city): ?array {
            $item->expiresAfter(1800);
            return $this->doFetchWeather($city);
        });
    }

    private function doFetchWeather(string $city): ?array
    {
        $url = 'https://api.openweathermap.org/data/2.5/weather?q='
            . urlencode($city)
            . '&appid=' . $this->apiKey
            . '&units=metric';

        try {
            $raw = $this->fetch($url);
            if ($raw === null) {
                return null;
            }
            $data = json_decode($raw, true);
            if (!is_array($data) || isset($data['cod']) && $data['cod'] !== 200) {
                return null;
            }

            return [
                'temp'        => round((float) ($data['main']['temp'] ?? 0), 1),
                'feels_like'  => round((float) ($data['main']['feels_like'] ?? 0), 1),
                'description' => ucfirst((string) ($data['weather'][0]['description'] ?? '')),
                'icon'        => 'https://openweathermap.org/img/wn/' . ($data['weather'][0]['icon'] ?? '01d') . '@2x.png',
                'city'        => (string) ($data['name'] ?? $city),
                'humidity'    => (int) ($data['main']['humidity'] ?? 0),
                'wind_speed'  => round((float) ($data['wind']['speed'] ?? 0), 1),
            ];
        } catch (\Throwable $e) {
            $this->logger->warning('WeatherService: failed', ['city' => $city, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function fetch(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 8,
                    CURLOPT_HTTPHEADER     => ['User-Agent: TravagirApp/1.0'],
                ]);
                $result = curl_exec($ch);
                curl_close($ch);
                return is_string($result) ? $result : null;
            }
        }
        $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true, 'header' => 'User-Agent: TravagirApp/1.0']]);
        $result = @file_get_contents($url, false, $ctx);
        return is_string($result) ? $result : null;
    }
}
