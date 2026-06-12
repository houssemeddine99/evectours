<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class BookingComService
{
    private const BASE_URL = 'https://booking-com15.p.rapidapi.com';
    private const CACHE_TTL = 86400; // 24h for destination lookups

    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiHost,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {}

    /** @return array<mixed> */
    public function searchFlightDestinations(string $query): array
    {
        return $this->cache->get('flight_dest_' . md5($query), function (ItemInterface $item) use ($query) {
            $item->expiresAfter(self::CACHE_TTL);
            $data = $this->request('/api/v1/flights/searchDestination', ['query' => $query]);
            return $data['data'] ?? [];
        });
    }

    /** @return array<mixed> */
    public function searchHotelDestinations(string $query): array
    {
        // tn_ cache key so previously-cached unfiltered (worldwide) results aren't reused.
        return $this->cache->get('hotel_dest_tn_' . md5($query), function (ItemInterface $item) use ($query) {
            $item->expiresAfter(self::CACHE_TTL);
            $data = $this->request('/api/v1/hotels/searchDestination', ['query' => $query]);
            $results = $data['data'] ?? [];

            // Tunisia only — drop any international destinations.
            return array_values(array_filter($results, static function ($d): bool {
                $cc      = strtolower((string) ($d['cc1'] ?? ''));
                $country = strtolower((string) ($d['country'] ?? ''));
                return $cc === 'tn' || $country === 'tunisia';
            }));
        });
    }

    /**
     * @param array<mixed> $params
     * @return array<mixed>
     */
    public function searchFlights(array $params): array
    {
        $data = $this->request('/api/v1/flights/searchFlights', array_filter([
            'fromId'      => $params['fromId'] ?? '',
            'toId'        => $params['toId'] ?? '',
            'departDate'  => $params['departDate'] ?? '',
            'returnDate'  => $params['returnDate'] ?? null,
            'adults'      => $params['adults'] ?? 1,
            'cabinClass'  => $params['cabinClass'] ?? 'ECONOMY',
            'currency'    => 'USD',
        ]));

        $offers = $data['data']['flightOffers'] ?? [];
        return array_map([$this, 'mapFlight'], $offers);
    }

    /**
     * @param array<mixed> $params
     * @return array<mixed>
     */
    public function searchHotels(array $params): array
    {
        $query = array_filter([
            'dest_id'       => $params['dest_id'] ?? '',
            'search_type'   => $params['search_type'] ?? 'city',
            'arrival_date'  => $params['arrival_date'] ?? '',
            'departure_date'=> $params['departure_date'] ?? '',
            'adults'        => $params['adults'] ?? 2,
            'room_qty'      => $params['rooms'] ?? 1,
            'currency_code' => 'USD',
        ]);

        // Children ages, e.g. "5,10" — Booking.com requires an age per child.
        if (!empty($params['children_age'])) {
            $query['children_age'] = $params['children_age'];
        }

        $data = $this->request('/api/v1/hotels/searchHotels', $query);

        $hotels = $data['data']['hotels'] ?? [];
        return array_map([$this, 'mapHotel'], $hotels);
    }

    /**
     * Available room types/offers for one hotel on the given dates.
     * @param array<mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function getRoomList(array $params): array
    {
        $hotelId = (string) ($params['hotel_id'] ?? '');
        if ($hotelId === '') {
            return [];
        }

        // Cache 30 min (prices/availability change) and to spare the API quota.
        $cacheKey = 'rooms_' . md5((string) json_encode($params));
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($params, $hotelId) {
            $item->expiresAfter(1800);

            $q = [
                'hotel_id'       => $hotelId,
                'arrival_date'   => $params['arrival_date'] ?? '',
                'departure_date' => $params['departure_date'] ?? '',
                'adults'         => $params['adults'] ?? 2,
                'room_qty'       => $params['rooms'] ?? 1,
                'units'          => 'metric',
                'currency_code'  => 'USD',
                'languagecode'   => 'en-us',
            ];
            if (!empty($params['children_age'])) {
                $q['children_age'] = $params['children_age'];
            }

            $data     = $this->request('/api/v1/hotels/getRoomList', $q);
            $blocks   = $data['data']['block'] ?? [];
            $currency = $data['data']['currency_code'] ?? 'USD';

            // Dedupe by room name, keep the cheapest offer per room type.
            $byName = [];
            foreach ($blocks as $b) {
                if (!is_array($b)) {
                    continue;
                }
                $name  = (string) ($b['room_name'] ?? $b['name'] ?? 'Room');
                $price = $b['product_price_breakdown']['gross_amount']['value'] ?? null;
                $room  = [
                    'name'       => $name,
                    'price'      => $price,
                    'currency'   => $currency,
                    'meal'       => $this->mealLabel((string) ($b['mealplan'] ?? '')),
                    'refundable' => (bool) ($b['refundable'] ?? false),
                    'surface'    => $b['room_surface_in_m2'] ?? null,
                ];
                if (!isset($byName[$name]) || ($price !== null && $price < ($byName[$name]['price'] ?? INF))) {
                    $byName[$name] = $room;
                }
            }

            return array_values($byName);
        });
    }

    private function mealLabel(string $mealplan): string
    {
        $mp = strtolower($mealplan);
        if (str_contains($mp, 'breakfast'))                                   return 'Breakfast included';
        if (str_contains($mp, 'all inclusive') || str_contains($mp, 'all-inclusive')) return 'All inclusive';
        if (str_contains($mp, 'full board'))                                  return 'Full board';
        if (str_contains($mp, 'half board'))                                  return 'Half board';
        return 'Room only';
    }

    /**
     * @param array<mixed> $offer
     * @return array<mixed>
     */
    private function mapFlight(array $offer): array
    {
        $segments = $offer['segments'] ?? [];
        $firstSeg = $segments[0] ?? [];
        $lastSeg  = end($segments) ?: [];

        $legs = $firstSeg['legs'] ?? [];
        $firstLeg = $legs[0] ?? [];
        $carriers = $firstLeg['carriersData'] ?? [];
        $carrier  = $carriers[0] ?? [];

        $stops = max(0, count($legs) - 1);

        return [
            'airline'      => $carrier['name'] ?? 'Unknown Airline',
            'airline_code' => $carrier['code'] ?? '',
            'airline_logo' => $carrier['logo'] ?? '',
            'flight_no'    => ($carrier['code'] ?? '') . ($firstLeg['flightInfo']['flightNumber'] ?? ''),
            'depart_time'  => $firstSeg['departureTime'] ?? '',
            'arrive_time'  => $lastSeg['arrivalTime'] ?? '',
            'cabin'        => $firstLeg['cabinClass'] ?? 'ECONOMY',
            'stops'        => $stops,
            'stop_label'   => $stops === 0 ? 'Nonstop' : ($stops . ' stop' . ($stops > 1 ? 's' : '')),
            'duration'     => $this->formatDuration($firstSeg['totalTime'] ?? 0),
            'price'        => $offer['priceBreakdown']['total']['units'] ?? null,
            'currency'     => $offer['priceBreakdown']['total']['currencyCode'] ?? 'USD',
            'from'         => $firstSeg['departureAirport']['code'] ?? '',
            'from_city'    => $firstSeg['departureAirport']['cityName'] ?? '',
            'to'           => $lastSeg['arrivalAirport']['code'] ?? '',
            'to_city'      => $lastSeg['arrivalAirport']['cityName'] ?? '',
            'baggage'      => $offer['includedProducts']['areFlightIncludedProductsAvailable'] ?? false,
        ];
    }

    /**
     * @param array<mixed> $item
     * @return array<mixed>
     */
    private function mapHotel(array $item): array
    {
        $prop  = $item['property'] ?? [];
        $price = $prop['priceBreakdown']['grossPrice'] ?? [];

        return [
            'hotel_id'      => $item['hotel_id'] ?? ($prop['id'] ?? null),
            'name'          => $prop['name'] ?? 'Unknown Hotel',
            'review_score'  => $prop['reviewScore'] ?? null,
            'review_count'  => $prop['reviewCount'] ?? 0,
            'review_word'   => $prop['reviewScoreWord'] ?? '',
            'price'         => $prop['priceBreakdown']['grossPrice']['value'] ?? null,
            'currency'      => $prop['priceBreakdown']['grossPrice']['currency'] ?? 'USD',
            'photo'         => ($prop['photoUrls'][0] ?? null),
            'checkin'       => $prop['checkinDate'] ?? '',
            'checkout'      => $prop['checkoutDate'] ?? '',
            'country'       => $prop['countryCode'] ?? '',
            'latitude'      => $prop['latitude'] ?? null,
            'longitude'     => $prop['longitude'] ?? null,
        ];
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) return '';
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        return $h . 'h ' . str_pad((string)$m, 2, '0', STR_PAD_LEFT) . 'm';
    }

    /**
     * @param array<mixed> $params
     * @return array<mixed>
     */
    private function request(string $path, array $params = []): array
    {
        $url = self::BASE_URL . $path . '?' . http_build_query($params);

        $ch = curl_init($url);
        if ($ch === false) {
            $this->logger->error('BookingComService failed to initialise cURL');
            return [];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'X-RapidAPI-Key: ' . $this->apiKey,
                'X-RapidAPI-Host: ' . $this->apiHost,
            ],
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $this->logger->error('BookingComService cURL error: ' . $err);
            return [];
        }

        if ($code !== 200) {
            $this->logger->warning('BookingComService HTTP ' . $code . ' for ' . $path);
            return [];
        }

        if (!is_string($body)) {
            $this->logger->error('BookingComService unexpected non-string response for ' . $path);
            return [];
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            $this->logger->error('BookingComService invalid JSON for ' . $path);
            return [];
        }

        return $data;
    }
}
