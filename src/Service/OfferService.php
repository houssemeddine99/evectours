<?php

namespace App\Service;

use App\Repository\OfferRepository;

class OfferService
{
    public function __construct(
        private readonly OfferRepository $offerRepository,
    ) {
    }

    public function getActiveOffers(): array
    {
        try {
            $offers = $this->offerRepository->findActiveOffers();
        } catch (\Throwable) {
            $offers = [];
        }

        if ($offers === []) {
            return $this->fallbackOffers();
        }

        $normalized = [];

        foreach ($offers as $offer) {
            $voyage = $offer->getVoyage();
            if ($voyage === null) {
                continue;
            }

            $normalized[] = [
                'id' => $offer->getId(),
                'title' => $offer->getTitle(),
                'description' => $offer->getDescription(),
                'discount_percentage' => (float) ($offer->getDiscountPercentage() ?? 0),
                'start_date' => $offer->getStartDate()?->format('Y-m-d'),
                'end_date' => $offer->getEndDate()?->format('Y-m-d'),
                'voyage_id' => $voyage->getId(),
                'voyage_title' => $voyage->getTitle(),
                'destination' => $voyage->getDestination(),
                'price' => (float) ($voyage->getPrice() ?? 0),
                'image_url' => ($voyage->getImageUrl()[0] ?? null) ?? 'https://images.unsplash.com/photo-1488646953014-85cb44e25828?auto=format&amp;fit=crop&amp;w=1200&amp;q=80',
            ];
        }

        return $normalized === [] ? $this->fallbackOffers() : $normalized;
    }

    private function fallbackOffers(): array
    {
        return [
            [
                'id' => 1,
                'title' => 'Spring Escape',
                'description' => 'Book before month end and enjoy a city-break discount.',
                'discount_percentage' => 15,
                'start_date' => '2026-03-01',
                'end_date' => '2026-05-30',
                'voyage_id' => 1,
                'voyage_title' => 'Magical Marrakech',
                'destination' => 'Marrakech, Morocco',
                'price' => 950,
                'image_url' => 'https://images.unsplash.com/photo-1597212618440-806262de4f6b?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'id' => 2,
                'title' => 'Family Summer Offer',
                'description' => 'Family package with airport transfer included.',
                'discount_percentage' => 20,
                'start_date' => '2026-06-01',
                'end_date' => '2026-08-31',
                'voyage_id' => 2,
                'voyage_title' => 'Santorini Sunsets',
                'destination' => 'Santorini, Greece',
                'price' => 1400,
                'image_url' => 'https://images.unsplash.com/photo-1570077188670-e3a8d69ac5ff?auto=format&fit=crop&w=1200&q=80',
            ],
        ];
    }
}