<?php

namespace App\Service;

use App\Repository\VoyageRepository;

class VoyageService
{
    public function __construct(
        private readonly VoyageRepository $voyageRepository,
    ) {
    }

    public function getFeaturedVoyages(int $limit = 3): array
    {
        try {
            $voyages = $this->voyageRepository->findFeatured($limit);
        } catch (\Throwable) {
            $voyages = [];
        }

        if ($voyages === []) {
            return array_slice($this->fallbackVoyages(), 0, $limit);
        }

        return array_map(fn ($voyage) => $this->mapVoyage($voyage), $voyages);
    }

    public function getAllVoyages(): array
    {
        try {
            $voyages = $this->voyageRepository->findAllOrdered();
        } catch (\Throwable) {
            $voyages = [];
        }

        if ($voyages === []) {
            return $this->fallbackVoyages();
        }

        return array_map(fn ($voyage) => $this->mapVoyage($voyage), $voyages);
    }

    public function getVoyages(int $page = 1, int $limit = 12): array
    {
        try {
            $offset = ($page - 1) * $limit;
            $voyages = $this->voyageRepository->findBy([], ['createdAt' => 'DESC'], $limit, $offset);
        } catch (\Throwable) {
            $voyages = [];
        }

        if ($voyages === []) {
            return array_slice($this->fallbackVoyages(), $offset, $limit);
        }

        return array_map(fn ($voyage) => $this->mapVoyage($voyage), $voyages);
    }

    public function getTotalVoyages(): int
    {
        try {
            return $this->voyageRepository->count([]);
        } catch (\Throwable) {
            return count($this->fallbackVoyages());
        }
    }

    public function getVoyageById(int $id): ?array
    {
        try {
            $voyage = $this->voyageRepository->find($id);
        } catch (\Throwable) {
            $voyage = null;
        }

        if ($voyage !== null) {
            $mapped = $this->mapVoyage($voyage);
            $mapped['activities'] = [];

            foreach ($voyage->getActivities() as $activity) {
                $mapped['activities'][] = [
                    'name' => $activity->getName(),
                    'description' => $activity->getDescription(),
                    'duration_hours' => $activity->getDurationHours(),
                    'price_per_person' => $activity->getPricePerPerson(),
                ];
            }

            return $mapped;
        }

        foreach ($this->fallbackVoyages() as $fallback) {
            if ((int) $fallback['id'] === $id) {
                $fallback['activities'] = [
                    ['name' => 'Guided city tour', 'description' => 'Visit iconic places with a local guide.', 'duration_hours' => 3, 'price_per_person' => 20],
                    ['name' => 'Local food discovery', 'description' => 'Taste local specialties and street food.', 'duration_hours' => 2, 'price_per_person' => 35],
                ];

                return $fallback;
            }
        }

        return null;
    }

    private function mapVoyage(object $voyage): array
    {
        return [
            'id' => $voyage->getId(),
            'title' => $voyage->getTitle(),
            'description' => $voyage->getDescription(),
            'destination' => $voyage->getDestination(),
            'start_date' => $voyage->getStartDate()?->format('Y-m-d'),
            'end_date' => $voyage->getEndDate()?->format('Y-m-d'),
            'price' => $voyage->getPrice(),
            'image_url' => $voyage->getImageUrl()[0] ?? null,
        ];
    }

    private function fallbackVoyages(): array
    {
        return [
            [
                'id' => 1,
                'title' => 'Magical Marrakech',
                'description' => 'Explore souks, palaces, and desert evenings in a 5-day curated trip.',
                'destination' => 'Marrakech, Morocco',
                'start_date' => '2026-04-10',
                'end_date' => '2026-04-15',
                'price' => 950,
                'image_url' => 'https://images.unsplash.com/photo-1597212618440-806262de4f6b?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'id' => 2,
                'title' => 'Santorini Sunsets',
                'description' => 'A romantic 6-day escape with sea-view stays and guided island tours.',
                'destination' => 'Santorini, Greece',
                'start_date' => '2026-06-05',
                'end_date' => '2026-06-11',
                'price' => 1400,
                'image_url' => 'https://images.unsplash.com/photo-1570077188670-e3a8d69ac5ff?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'id' => 3,
                'title' => 'Istanbul Heritage Tour',
                'description' => 'Experience Ottoman architecture, Bosphorus cruises, and rich street food.',
                'destination' => 'Istanbul, Turkey',
                'start_date' => '2026-05-12',
                'end_date' => '2026-05-18',
                'price' => 1100,
                'image_url' => 'https://images.unsplash.com/photo-1524231757912-21f4fe3a7200?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'id' => 4,
                'title' => 'Dubai Adventure',
                'description' => 'Luxury city experience with desert safari and premium activities.',
                'destination' => 'Dubai, UAE',
                'start_date' => '2026-09-03',
                'end_date' => '2026-09-08',
                'price' => 1700,
                'image_url' => 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?auto=format&fit=crop&w=1200&q=80',
            ],
        ];
    }
}