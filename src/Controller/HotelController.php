<?php

namespace App\Controller;

use App\Service\CurrencyService;
use App\Service\HotelbedsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Live hotel search powered by Hotelbeds APItude (real Tunisian inventory).
 * Replaces the previous RapidAPI hotel search; keeps the `travel_hotels` route name
 * so existing nav links keep working.
 */
class HotelController extends AbstractController
{
    /** Popular Tunisian destinations (Hotelbeds destination codes) for chips/tiles. */
    private const POPULAR = [
        ['code' => 'HMM', 'name' => 'Hammamet',            'img' => 'https://images.unsplash.com/photo-1590523278191-995cbcda646b?auto=format&fit=crop&w=800&q=80'],
        ['code' => 'DJE', 'name' => 'Djerba',              'img' => 'https://images.unsplash.com/photo-1518548419970-58e3b4079ab2?auto=format&fit=crop&w=800&q=80'],
        ['code' => 'TN1', 'name' => 'Sousse / Kantaoui',   'img' => 'https://images.unsplash.com/photo-1512958789358-4ba50a4d2f6b?auto=format&fit=crop&w=800&q=80'],
        ['code' => 'MIR', 'name' => 'Monastir – Skanès',   'img' => 'https://images.unsplash.com/photo-1533105079780-92b9be482077?auto=format&fit=crop&w=800&q=80'],
        ['code' => 'TOE', 'name' => 'Tunis – Carthage',    'img' => 'https://images.unsplash.com/photo-1605540436563-5bca919ae766?auto=format&fit=crop&w=800&q=80'],
        ['code' => 'MH1', 'name' => 'Mahdia',              'img' => 'https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?auto=format&fit=crop&w=800&q=80'],
        ['code' => 'NAB', 'name' => 'Nabeul',              'img' => 'https://images.unsplash.com/photo-1596394516093-501ba68a0ba6?auto=format&fit=crop&w=800&q=80'],
        ['code' => 'TAK', 'name' => 'Tabarka',             'img' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80'],
    ];

    public function __construct(
        private readonly HotelbedsService $hotels,
        private readonly CurrencyService $currency,
    ) {}

    #[Route('/hotels', name: 'travel_hotels', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $q = [
            'dest'     => strtoupper(trim((string) $request->query->get('dest', ''))),
            'destName' => trim((string) $request->query->get('dest_name', '')),
            'checkIn'  => trim((string) $request->query->get('checkIn', '')),
            'checkOut' => trim((string) $request->query->get('checkOut', '')),
            'adults'   => max(1, (int) $request->query->get('adults', 2)),
            'children' => max(0, min(6, (int) $request->query->get('children', 0))),
            'rooms'    => max(1, (int) $request->query->get('rooms', 1)),
        ];

        $results  = null;
        $error    = null;
        $searched = false;
        $nights   = 0;

        if ($q['dest'] !== '' && $q['checkIn'] !== '' && $q['checkOut'] !== '') {
            $searched = true;
            $today = (new \DateTime('today'))->format('Y-m-d');
            if ($q['checkIn'] < $today) {
                $error = 'Check-in date cannot be in the past.';
            } elseif ($q['checkOut'] <= $q['checkIn']) {
                $error = 'Check-out must be after check-in.';
            } elseif (!$this->hotels->isConfigured()) {
                $error = 'Hotel search is not available right now. Please try again later.';
            } else {
                $nights = (int) max(1, (strtotime($q['checkOut']) - strtotime($q['checkIn'])) / 86400);
                $hotels = $this->hotels->searchHotels([
                    'destination' => $q['dest'],
                    'checkIn'     => $q['checkIn'],
                    'checkOut'    => $q['checkOut'],
                    'adults'      => $q['adults'],
                    'children'    => $q['children'],
                    'rooms'       => $q['rooms'],
                ]);
                $results = $this->withPrices($hotels, $nights);
            }
        }

        return $this->render('travel/hotels.html.twig', [
            'active_nav' => 'hotels',
            'popular'    => self::POPULAR,
            'results'    => $results,
            'searched'   => $searched,
            'error'      => $error,
            'nights'     => $nights,
            'q'          => $q,
            'configured' => $this->hotels->isConfigured(),
        ]);
    }

    #[Route('/hotels/suggest', name: 'hotel_suggest', methods: ['GET'])]
    public function suggest(Request $request): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));
        return $this->json($this->hotels->searchDestinations($query));
    }

    #[Route('/hotel/{code}', name: 'hotel_detail', methods: ['GET'], requirements: ['code' => '\d+'])]
    public function detail(string $code, Request $request): Response
    {
        $params = [
            'checkIn'  => trim((string) $request->query->get('checkIn', '')),
            'checkOut' => trim((string) $request->query->get('checkOut', '')),
            'adults'   => max(1, (int) $request->query->get('adults', 2)),
            'children' => max(0, min(6, (int) $request->query->get('children', 0))),
            'rooms'    => max(1, (int) $request->query->get('rooms', 1)),
        ];

        $hotel = $this->hotels->hotelDetail($code, $params);
        if ($hotel === null || $hotel['name'] === '') {
            throw $this->createNotFoundException('Hotel not found.');
        }

        $nights = ($params['checkIn'] && $params['checkOut'])
            ? (int) max(1, (strtotime($params['checkOut']) - strtotime($params['checkIn'])) / 86400)
            : 0;

        // Convert room prices to the user's currency (total for the stay + per night).
        $userCurrency = $this->currency->getUserCurrency();
        foreach ($hotel['rooms'] as $i => $room) {
            if ($room['price'] !== null) {
                $cur = (string) $room['currency'];
                $hotel['rooms'][$i]['price_display'] = $this->currency->formatFrom((float) $room['price'], $cur, $userCurrency);
                $hotel['rooms'][$i]['price_night_display'] = $nights > 0
                    ? $this->currency->formatFrom((float) $room['price'] / $nights, $cur, $userCurrency)
                    : null;
            } else {
                $hotel['rooms'][$i]['price_display'] = null;
                $hotel['rooms'][$i]['price_night_display'] = null;
            }
        }

        return $this->render('travel/hotel_detail.html.twig', [
            'active_nav' => 'hotels',
            'hotel'      => $hotel,
            'q'          => $params,
            'nights'     => $nights,
        ]);
    }

    /**
     * Add `price_display` (total for the stay) and `price_night_display` (per night),
     * both converted to the user's currency.
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function withPrices(array $items, int $nights = 0): array
    {
        $userCurrency = $this->currency->getUserCurrency();
        foreach ($items as $i => $item) {
            $price = $item['price'] ?? null;
            if ($price !== null && $price !== '') {
                $cur = (string) ($item['currency'] ?? 'EUR');
                $items[$i]['price_display'] = $this->currency->formatFrom((float) $price, $cur, $userCurrency);
                $items[$i]['price_night_display'] = $nights > 0
                    ? $this->currency->formatFrom((float) $price / $nights, $cur, $userCurrency)
                    : null;
            } else {
                $items[$i]['price_display'] = null;
                $items[$i]['price_night_display'] = null;
            }
        }
        return $items;
    }
}
