<?php

namespace App\Controller;

use App\Service\BookingComService;
use App\Service\CurrencyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TravelSearchController extends AbstractController
{
    public function __construct(
        private readonly BookingComService $booking,
        private readonly CurrencyService $currency,
    ) {}

    /**
     * Add a `price_display` field (converted to the user's currency) to each result.
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function withConvertedPrices(array $items): array
    {
        $userCurrency = $this->currency->getUserCurrency();
        foreach ($items as $i => $item) {
            $price = $item['price'] ?? null;
            $items[$i]['price_display'] = ($price !== null && $price !== '')
                ? $this->currency->formatFrom((float) $price, (string) ($item['currency'] ?? 'USD'), $userCurrency)
                : null;
        }
        return $items;
    }

    #[Route('/travel-search', name: 'travel_search', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('travel/travel_search.html.twig', [
            'active_nav' => 'search',
        ]);
    }

    #[Route('/travel-search/flights', name: 'travel_search_flights', methods: ['POST'])]
    public function flights(Request $request): JsonResponse
    {
        if (!$this->rateLimit($request, 'flight_search', 10)) {
            return $this->json(['success' => false, 'error' => 'Too many requests. Please wait a moment.'], 429);
        }

        $fromId    = trim((string) $request->request->get('fromId', ''));
        $toId      = trim((string) $request->request->get('toId', ''));
        $depart    = trim((string) $request->request->get('departDate', ''));
        $return    = trim((string) $request->request->get('returnDate', '')) ?: null;
        $adults    = max(1, (int) $request->request->get('adults', 1));
        $cabin     = (string) $request->request->get('cabinClass', 'ECONOMY');

        if (!$fromId || !$toId || !$depart) {
            return $this->json(['success' => false, 'error' => 'Please fill in all required fields.'], 400);
        }

        $flights = $this->booking->searchFlights([
            'fromId'     => $fromId,
            'toId'       => $toId,
            'departDate' => $depart,
            'returnDate' => $return,
            'adults'     => $adults,
            'cabinClass' => strtoupper($cabin),
        ]);

        return $this->json([
            'success' => true,
            'count'   => count($flights),
            'flights' => $this->withConvertedPrices($flights),
        ]);
    }

    #[Route('/travel-search/hotels', name: 'travel_search_hotels', methods: ['POST'])]
    public function hotels(Request $request): JsonResponse
    {
        if (!$this->rateLimit($request, 'hotel_search', 10)) {
            return $this->json(['success' => false, 'error' => 'Too many requests. Please wait a moment.'], 429);
        }

        $destId    = trim((string) $request->request->get('dest_id', ''));
        $searchType= trim((string) $request->request->get('search_type', 'city'));
        $checkin   = trim((string) $request->request->get('arrival_date', ''));
        $checkout  = trim((string) $request->request->get('departure_date', ''));
        $adults    = max(1, (int) $request->request->get('adults', 2));
        $rooms     = max(1, (int) $request->request->get('rooms', 1));

        // Children ages: "5,10" — clamp each to 0..17, max 6 children.
        $childrenAge = '';
        $rawAges = (string) $request->request->get('children_age', '');
        if ($rawAges !== '') {
            $ages = array_slice(array_filter(
                array_map('trim', explode(',', $rawAges)),
                static fn($a): bool => $a !== '' && is_numeric($a)
            ), 0, 6);
            $ages = array_map(static fn($a): int => max(0, min(17, (int) $a)), $ages);
            $childrenAge = implode(',', $ages);
        }

        if (!$destId || !$checkin || !$checkout) {
            return $this->json(['success' => false, 'error' => 'Please fill in all required fields.'], 400);
        }

        // Date sanity (defend against tampered/invalid dates from the client).
        $today = (new \DateTime('today'))->format('Y-m-d');
        if ($checkin < $today) {
            return $this->json(['success' => false, 'error' => 'Check-in date cannot be in the past.'], 400);
        }
        if ($checkout <= $checkin) {
            return $this->json(['success' => false, 'error' => 'Check-out must be after check-in.'], 400);
        }

        $hotels = $this->booking->searchHotels([
            'dest_id'       => $destId,
            'search_type'   => $searchType,
            'arrival_date'  => $checkin,
            'departure_date'=> $checkout,
            'adults'        => $adults,
            'rooms'         => $rooms,
            'children_age'  => $childrenAge,
        ]);

        return $this->json([
            'success' => true,
            'count'   => count($hotels),
            'hotels'  => $this->withConvertedPrices($hotels),
        ]);
    }

    #[Route('/travel-search/flight-destinations', name: 'travel_search_flight_dest', methods: ['GET'])]
    public function flightDestinations(Request $request): JsonResponse
    {
        $query = trim($request->query->get('q', ''));
        if (strlen($query) < 2) {
            return $this->json([]);
        }
        $results = $this->booking->searchFlightDestinations($query);
        return $this->json($results);
    }

    #[Route('/travel-search/hotel-destinations', name: 'travel_search_hotel_dest', methods: ['GET'])]
    public function hotelDestinations(Request $request): JsonResponse
    {
        $query = trim($request->query->get('q', ''));
        if (strlen($query) < 2) {
            return $this->json([]);
        }
        $results = $this->booking->searchHotelDestinations($query);
        return $this->json($results);
    }

    private function rateLimit(Request $request, string $key, int $max): bool
    {
        $session = $request->getSession();
        $now     = time();
        $window  = (int) $session->get($key . '_window', 0);
        $count   = (int) $session->get($key . '_count', 0);

        if ($now - $window < 60) {
            if ($count >= $max) return false;
            $session->set($key . '_count', $count + 1);
        } else {
            $session->set($key . '_window', $now);
            $session->set($key . '_count', 1);
        }

        return true;
    }
}
