<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Hotelbeds APItude — live hotel availability, content (photos/description) and booking.
 *
 * Auth: every request carries `Api-key`, `X-Signature` (SHA-256 of apiKey+secret+unix-time)
 * and `Accept: application/json`. Base URL switches between the test ("PRUEBAS") and
 * production environments via the HOTELBEDS_ENV var.
 *
 * Degrades gracefully: with no credentials configured every call returns []/null so the
 * portal keeps rendering (empty states) instead of crashing.
 */
class HotelbedsService
{
    private const BOOKING_TEST = 'https://api.test.hotelbeds.com';
    private const BOOKING_LIVE = 'https://api.hotelbeds.com';
    private const PHOTO_BASE    = 'https://photos.hotelbeds.com/giata/';

    private const CONTENT_TTL = 604800; // 7 days — static content (names, photos) rarely changes
    private const DEST_TTL    = 604800; // 7 days — destination list
    private const AVAIL_TTL   = 900;    // 15 min — live prices/availability

    public function __construct(
        private readonly string $apiKey,
        private readonly string $secret,
        private readonly string $environment, // 'test' | 'live'
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {}

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->secret !== '';
    }

    private function baseUrl(): string
    {
        return strtolower($this->environment) === 'live' ? self::BOOKING_LIVE : self::BOOKING_TEST;
    }

    // ── Destinations (autocomplete) ───────────────────────────────────────

    /**
     * Tunisia destinations (Tunis, Djerba, Sousse, Hammamet…) filtered by a query.
     * Cached 7d — the Content API destination list is essentially static.
     * @return array<int, array{code:string,name:string,zone:string,country:string}>
     */
    public function searchDestinations(string $query, string $countryCode = 'TN'): array
    {
        if (!$this->isConfigured()) {
            return [];
        }
        $all = $this->cache->get('hb_dest_' . strtoupper($countryCode), function (ItemInterface $item) use ($countryCode) {
            $item->expiresAfter(self::DEST_TTL);
            $data = $this->get('/hotel-content-api/1.0/locations/destinations', [
                'fields'       => 'all',
                'language'     => 'ENG',
                'countryCodes' => strtoupper($countryCode),
                'from'         => 1,
                'to'           => 1000,
            ]);
            $out = [];
            foreach (($data['destinations'] ?? []) as $d) {
                $out[] = [
                    'code'    => (string) ($d['code'] ?? ''),
                    'name'    => (string) ($d['name']['content'] ?? $d['name'] ?? ''),
                    'zone'    => (string) ($d['countryCode'] ?? ''),
                    'country' => (string) ($d['countryCode'] ?? ''),
                ];
            }
            return $out;
        });

        $q = mb_strtolower(trim($query));
        if ($q === '') {
            return array_slice($all, 0, 12);
        }
        $matches = array_values(array_filter($all, static fn($d) => str_contains(mb_strtolower($d['name']), $q)));
        return array_slice($matches, 0, 12);
    }

    /**
     * Hotels matching a name query (e.g. "Alhambra Thalasso"). Backed by a cached
     * index of the whole country's hotels (name + code), rebuilt weekly.
     * @return array<int, array{code:string,name:string}>
     */
    public function searchHotelsByName(string $query, string $countryCode = 'TN'): array
    {
        if (!$this->isConfigured()) {
            return [];
        }
        $q = mb_strtolower(trim($query));
        if (mb_strlen($q) < 3) {
            return [];
        }

        $index = $this->cache->get('hb_hotelidx_' . strtoupper($countryCode), function (ItemInterface $item) use ($countryCode) {
            $item->expiresAfter(self::CONTENT_TTL);
            $out  = [];
            $from = 1;
            $step = 1000;
            do {
                $data   = $this->get('/hotel-content-api/1.0/hotels', [
                    'fields'      => 'code,name',
                    'language'    => 'ENG',
                    'countryCode' => strtoupper($countryCode),
                    'from'        => $from,
                    'to'          => $from + $step - 1,
                ]);
                $hotels = $data['hotels'] ?? [];
                foreach ($hotels as $h) {
                    $name = (string) ($h['name']['content'] ?? '');
                    if ($name !== '') {
                        $out[] = ['code' => (string) ($h['code'] ?? ''), 'name' => $name];
                    }
                }
                $total = (int) ($data['total'] ?? 0);
                $from += $step;
            } while ($from <= $total && !empty($hotels));
            return $out;
        });

        $matches = array_values(array_filter(
            $index,
            static fn($h): bool => str_contains(mb_strtolower($h['name']), $q)
        ));
        return array_slice($matches, 0, 8);
    }

    // ── Availability (search results) ─────────────────────────────────────

    /**
     * Live availability for a destination + dates + occupancy.
     * @param array{destination:string,checkIn:string,checkOut:string,adults?:int,children?:int,rooms?:int,childrenAges?:array<int>} $p
     * @return array<int, array<string, mixed>> hotel cards (code,name,stars,zone,price,currency,photo…)
     */
    public function searchHotels(array $p): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $destination = strtoupper(trim((string) ($p['destination'] ?? '')));
        $checkIn     = (string) ($p['checkIn'] ?? '');
        $checkOut    = (string) ($p['checkOut'] ?? '');
        if ($destination === '' || $checkIn === '' || $checkOut === '') {
            return [];
        }

        $rooms    = max(1, (int) ($p['rooms'] ?? 1));
        $adults   = max(1, (int) ($p['adults'] ?? 2));
        $children = max(0, (int) ($p['children'] ?? 0));

        $occupancy = ['rooms' => $rooms, 'adults' => $adults, 'children' => $children];
        if ($children > 0) {
            $ages = array_slice(array_map('intval', $p['childrenAges'] ?? []), 0, $children);
            $ages = array_pad($ages, $children, 8); // default age if missing
            $occupancy['paxes'] = array_map(static fn($age) => ['type' => 'CH', 'age' => max(0, min(17, $age))], $ages);
        }

        $body = [
            'stay'        => ['checkIn' => $checkIn, 'checkOut' => $checkOut],
            'occupancies' => [$occupancy],
            'destination' => ['code' => $destination],
        ];

        $cacheKey = 'hb_avail_' . md5((string) json_encode($body));
        $hotels = $this->cache->get($cacheKey, function (ItemInterface $item) use ($body) {
            $item->expiresAfter(self::AVAIL_TTL);
            $data = $this->post('/hotel-api/1.0/hotels', $body);
            return $data['hotels']['hotels'] ?? [];
        });

        $mapped = array_map([$this, 'mapAvailabilityHotel'], $hotels);

        // Enrich with a photo from the Content API (batched, cached long).
        $codes   = array_values(array_filter(array_map(static fn($h) => $h['code'] ?? null, $mapped)));
        $content = $this->contentFor($codes);
        foreach ($mapped as $i => $h) {
            $c = $content[(string) $h['code']] ?? null;
            $mapped[$i]['photo'] = $c['photo'] ?? null;
            $mapped[$i]['latitude']  = $c['latitude']  ?? null;
            $mapped[$i]['longitude'] = $c['longitude'] ?? null;
        }

        return $mapped;
    }

    /**
     * One hotel: static content (photos, description, facilities) + live rooms/rates.
     * @return array<string, mixed>|null
     */
    public function hotelDetail(string $code, array $p): ?array
    {
        if (!$this->isConfigured() || $code === '') {
            return null;
        }
        $content = $this->contentFor([$code])[$code] ?? null;

        // Availability for just this hotel → room offers with rateKeys (needed to book).
        $rooms = [];
        $checkIn  = (string) ($p['checkIn'] ?? '');
        $checkOut = (string) ($p['checkOut'] ?? '');
        if ($checkIn !== '' && $checkOut !== '') {
            $occupancy = [
                'rooms'    => max(1, (int) ($p['rooms'] ?? 1)),
                'adults'   => max(1, (int) ($p['adults'] ?? 2)),
                'children' => max(0, (int) ($p['children'] ?? 0)),
            ];
            $body = [
                'stay'        => ['checkIn' => $checkIn, 'checkOut' => $checkOut],
                'occupancies' => [$occupancy],
                'hotels'      => ['hotel' => [(int) $code]],
            ];
            $data  = $this->post('/hotel-api/1.0/hotels', $body);
            $hotel = $data['hotels']['hotels'][0] ?? null;
            if ($hotel) {
                foreach (($hotel['rooms'] ?? []) as $room) {
                    $rate = $room['rates'][0] ?? [];
                    $rooms[] = [
                        'name'       => $room['name'] ?? 'Room',
                        'board'      => $rate['boardName'] ?? '',
                        'rate_key'   => $rate['rateKey'] ?? '',
                        'price'      => $rate['net'] ?? null,
                        'currency'   => $hotel['currency'] ?? 'EUR',
                        'cancellable'=> ($rate['rateClass'] ?? '') !== 'NRF',
                    ];
                }
            }
        }

        return [
            'code'        => $code,
            'name'        => $content['name'] ?? '',
            'stars'       => $content['stars'] ?? null,
            'description' => $content['description'] ?? '',
            'address'     => $content['address'] ?? '',
            'zone'        => $content['zone'] ?? '',
            'photos'      => $content['photos'] ?? [],
            'latitude'    => $content['latitude'] ?? null,
            'longitude'   => $content['longitude'] ?? null,
            'rooms'       => $rooms,
        ];
    }

    // ── Booking (checkrate + book) ────────────────────────────────────────

    /**
     * Re-price a rate right before payment (prices/availability can move).
     * Returns the confirmed rate incl. a fresh BOOKABLE rateKey to book with.
     * @return array<string, mixed>|null
     */
    public function checkRate(string $rateKey): ?array
    {
        if (!$this->isConfigured() || $rateKey === '') {
            return null;
        }
        $data = $this->post('/hotel-api/1.0/checkrates', ['rooms' => [['rateKey' => $rateKey]]]);
        $hotel = $data['hotel'] ?? null;
        $rate  = $hotel['rooms'][0]['rates'][0] ?? null;
        if (!$hotel || !$rate) {
            return null;
        }
        return [
            'hotel_code'  => (string) ($hotel['code'] ?? ''),
            'hotel_name'  => (string) ($hotel['name'] ?? ''),
            'room_name'   => (string) ($hotel['rooms'][0]['name'] ?? 'Room'),
            'board'       => (string) ($rate['boardName'] ?? ''),
            'net'         => isset($rate['net']) ? (float) $rate['net'] : null,
            'currency'    => (string) ($hotel['currency'] ?? 'EUR'),
            'rate_key'    => (string) ($rate['rateKey'] ?? ''),
            'cancellable' => ($rate['rateClass'] ?? '') !== 'NRF',
            'adults'      => (int) ($rate['adults'] ?? 2),
            'children'    => (int) ($rate['children'] ?? 0),
        ];
    }

    /**
     * Confirm a booking with Hotelbeds after payment succeeds.
     * @param array{rateKey:string,holderName:string,holderSurname:string,adults:int,children:int,childrenAges?:array<int>,clientReference?:string,remark?:string} $p
     * @return array{reference?:string,status?:string,net?:mixed,currency?:string,error?:string}
     */
    public function book(array $p): array
    {
        if (!$this->isConfigured()) {
            return ['error' => 'Hotel booking is not configured.'];
        }

        // Build paxes for the room: lead pax = holder, extra adults = generic guests.
        $paxes  = [];
        $adults = max(1, (int) ($p['adults'] ?? 1));
        for ($i = 1; $i <= $adults; $i++) {
            $paxes[] = [
                'roomId'  => 1,
                'type'    => 'AD',
                'name'    => $i === 1 ? $p['holderName'] : 'Guest',
                'surname' => $i === 1 ? $p['holderSurname'] : (string) $i,
            ];
        }
        $ages = array_values($p['childrenAges'] ?? []);
        for ($i = 0; $i < (int) ($p['children'] ?? 0); $i++) {
            $paxes[] = [
                'roomId'  => 1,
                'type'    => 'CH',
                'age'     => (int) ($ages[$i] ?? 8),
                'name'    => 'Child',
                'surname' => (string) ($i + 1),
            ];
        }

        $body = [
            'holder'          => ['name' => $p['holderName'], 'surname' => $p['holderSurname']],
            'rooms'           => [['rateKey' => $p['rateKey'], 'paxes' => $paxes]],
            'clientReference' => $p['clientReference'] ?? 'EVEC',
            'remark'          => $p['remark'] ?? '',
            'tolerance'       => 2.0, // accept up to 2% price movement vs checkrate
        ];

        $data    = $this->post('/hotel-api/1.0/bookings', $body);
        $booking = $data['booking'] ?? null;
        if (!$booking || empty($booking['reference'])) {
            $msg = $data['error']['message'] ?? 'Hotelbeds booking failed.';
            $this->logger->error('HotelbedsService booking failed: ' . $msg);
            return ['error' => $msg];
        }
        return [
            'reference' => (string) $booking['reference'],
            'status'    => (string) ($booking['status'] ?? ''),
            'net'       => $booking['totalNet'] ?? null,
            'currency'  => (string) ($booking['currency'] ?? 'EUR'),
        ];
    }

    /**
     * Batch content lookup (photos/description/coords) keyed by hotel code. Cached 7d.
     * @param array<int|string> $codes
     * @return array<string, array<string, mixed>>
     */
    private function contentFor(array $codes): array
    {
        $codes = array_values(array_unique(array_map('strval', $codes)));
        if (!$codes || !$this->isConfigured()) {
            return [];
        }
        $cacheKey = 'hb_content_' . md5(implode(',', $codes));
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($codes) {
            $item->expiresAfter(self::CONTENT_TTL);
            $data = $this->get('/hotel-content-api/1.0/hotels', [
                'fields'   => 'all',
                'language' => 'ENG',
                'codes'    => implode(',', $codes),
                'from'     => 1,
                'to'       => max(1, count($codes)),
            ]);
            $out = [];
            foreach (($data['hotels'] ?? []) as $h) {
                $code   = (string) ($h['code'] ?? '');
                $photos = [];
                foreach (($h['images'] ?? []) as $img) {
                    if (!empty($img['path'])) {
                        $photos[] = self::PHOTO_BASE . $img['path'];
                    }
                }
                $out[$code] = [
                    'name'        => (string) ($h['name']['content'] ?? ''),
                    'stars'       => $this->starsFromCategory((string) ($h['categoryCode'] ?? '')),
                    'description' => (string) ($h['description']['content'] ?? ''),
                    'address'     => (string) ($h['address']['content'] ?? ''),
                    'zone'        => (string) ($h['zoneName'] ?? ($h['destinationName']['content'] ?? '')),
                    'photo'       => $photos[0] ?? null,
                    'photos'      => array_slice($photos, 0, 12),
                    'latitude'    => $h['coordinates']['latitude'] ?? null,
                    'longitude'   => $h['coordinates']['longitude'] ?? null,
                ];
            }
            return $out;
        });
    }

    // ── Mapping helpers ───────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $h
     * @return array<string, mixed>
     */
    private function mapAvailabilityHotel(array $h): array
    {
        return [
            'code'         => (string) ($h['code'] ?? ''),
            'name'         => (string) ($h['name'] ?? 'Hotel'),
            'stars'        => $this->starsFromCategory((string) ($h['categoryCode'] ?? '')),
            'category'     => (string) ($h['categoryName'] ?? ''),
            'zone'         => (string) ($h['zoneName'] ?? ''),
            'destination'  => (string) ($h['destinationName'] ?? ''),
            'price'        => isset($h['minRate']) ? (float) $h['minRate'] : null,
            'currency'     => (string) ($h['currency'] ?? 'EUR'),
            'photo'        => null, // filled from content
        ];
    }

    /** "4EST" / "H4_5" → 4. Returns null when not derivable. */
    private function starsFromCategory(string $categoryCode): ?int
    {
        if (preg_match('/(\d)/', $categoryCode, $m)) {
            $n = (int) $m[1];
            return ($n >= 1 && $n <= 5) ? $n : null;
        }
        return null;
    }

    // ── HTTP ──────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path . '?' . http_build_query($query));
    }

    /** @param array<mixed> $body @return array<string, mixed> */
    private function post(string $path, array $body): array
    {
        return $this->request('POST', $path, $body);
    }

    /**
     * @param array<mixed>|null $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $signature = hash('sha256', $this->apiKey . $this->secret . time());
        $headers = [
            'Api-key: ' . $this->apiKey,
            'X-Signature: ' . $signature,
            'Accept: application/json',
            'Accept-Encoding: gzip',
        ];

        $ch = curl_init($this->baseUrl() . $path);
        if ($ch === false) {
            $this->logger->error('HotelbedsService failed to init cURL');
            return [];
        }
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_ENCODING       => 'gzip',
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_POSTFIELDS] = (string) json_encode($body);
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);

        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $this->logger->error('HotelbedsService cURL error: ' . $err);
            return [];
        }
        if ($code < 200 || $code >= 300) {
            $this->logger->warning('HotelbedsService HTTP ' . $code . ' for ' . $path . ' :: ' . (is_string($resp) ? substr($resp, 0, 300) : ''));
            return [];
        }
        if (!is_string($resp)) {
            return [];
        }
        $data = json_decode($resp, true);
        return is_array($data) ? $data : [];
    }
}
