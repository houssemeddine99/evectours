<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CarbonFootprintService;
use App\Service\CountryInfoService;
use App\Service\OfferService;
use App\Service\VoyageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * JSON API consumed by the Evec Tours mobile app (Flutter).
 * Phase 1: read-only browsing (voyages, offers). Auth/booking/payment added later.
 */
#[Route('/api/v1')]
class ApiController extends AbstractController
{
    public function __construct(
        private readonly VoyageService $voyageService,
        private readonly OfferService $offerService,
        private readonly CarbonFootprintService $carbonService,
        private readonly CountryInfoService $countryInfo,
    ) {
    }

    #[Route('/ping', name: 'api_v1_ping', methods: ['GET'])]
    public function ping(): JsonResponse
    {
        return $this->json(['ok' => true, 'app' => 'Evec Tours API', 'version' => 1]);
    }

    #[Route('/voyages', name: 'api_v1_voyages', methods: ['GET'])]
    public function voyages(): JsonResponse
    {
        $voyages = $this->voyageService->getAllActiveVoyages();
        return $this->json([
            'voyages' => array_map([$this, 'listItem'], $voyages),
        ]);
    }

    #[Route('/voyages/{slug}', name: 'api_v1_voyage', methods: ['GET'])]
    public function voyage(string $slug): JsonResponse
    {
        $voyage = $this->voyageService->getVoyageBySlug($slug);
        if ($voyage === null) {
            return $this->json(['error' => 'Voyage not found'], 404);
        }

        $images = is_array($voyage['image_url'] ?? null) ? array_values($voyage['image_url']) : [];
        $destination = (string) ($voyage['destination'] ?? '');
        $carbon = $this->carbonService->calculate($destination, 1);

        // Offer attached to this voyage (if any)
        $offer = null;
        foreach ($this->offerService->getActiveOffers() as $o) {
            if ((int) ($o['voyage_id'] ?? 0) === (int) ($voyage['id'] ?? -1)) {
                $offer = $this->offerItem($o);
                break;
            }
        }

        return $this->json([
            'slug'          => $voyage['slug'] ?? $slug,
            'title'         => $voyage['title'] ?? '',
            'destination'   => $destination,
            'description'   => (string) ($voyage['description'] ?? ''),
            'price'         => $voyage['price'] ?? null,
            'base_price'    => $voyage['base_price'] ?? null,
            'duration_days' => $voyage['duration_days'] ?? null,
            'start_date'    => $voyage['start_date'] ?? null,
            'end_date'      => $voyage['end_date'] ?? null,
            'images'        => $images,
            'image'         => $images[0] ?? null,
            'carbon'        => [
                'co2_per_person' => $carbon['co2_per_person'] ?? null,
                'distance_km'    => $carbon['distance_km'] ?? null,
                'label'          => $carbon['label'] ?? null,
            ],
            'country'       => $this->countryInfo->forDestination($destination),
            'offer'         => $offer,
        ]);
    }

    #[Route('/offers', name: 'api_v1_offers', methods: ['GET'])]
    public function offers(): JsonResponse
    {
        $offers = array_map([$this, 'offerItem'], $this->offerService->getActiveOffers());
        return $this->json(['offers' => $offers]);
    }

    /** @param array<string,mixed> $v */
    private function listItem(array $v): array
    {
        $images = is_array($v['image_url'] ?? null) ? array_values($v['image_url']) : [];
        $desc = trim(strip_tags((string) ($v['description'] ?? '')));
        return [
            'slug'          => $v['slug'] ?? '',
            'title'         => $v['title'] ?? '',
            'destination'   => $v['destination'] ?? '',
            'price'         => $v['price'] ?? null,
            'duration_days' => $v['duration_days'] ?? null,
            'image'         => $images[0] ?? null,
            'summary'       => mb_substr($desc, 0, 160),
        ];
    }

    /** @param array<string,mixed> $o */
    private function offerItem(array $o): array
    {
        return [
            'id'                  => $o['id'] ?? null,
            'title'               => $o['title'] ?? '',
            'description'         => (string) ($o['description'] ?? ''),
            'discount_percentage' => $o['discount_percentage'] ?? null,
            'voyage_title'        => $o['voyage_title'] ?? null,
            'voyage_slug'         => $o['voyage_slug'] ?? null,
            'end_date'            => $o['end_date'] ?? null,
        ];
    }
}
