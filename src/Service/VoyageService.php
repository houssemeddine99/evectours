<?php

namespace App\Service;

use App\Repository\VoyageImageRepository;
use App\Repository\VoyageRepository;

class VoyageService
{
    public function __construct(
        private readonly VoyageRepository $voyageRepository,
        private readonly VoyageImageRepository $voyageImageRepository,
    ) {
    }

    public function getFeaturedVoyages(int $limit = 3): array
    {
        try {
            $voyages = $this->voyageRepository->findFeatured($limit);
        } catch (\Throwable) {
            $voyages = [];
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

        return array_map(fn ($voyage) => $this->mapVoyage($voyage), $voyages);
    }

    public function getTotalVoyages(): int
    {
        try {
            return $this->voyageRepository->count([]);
        } catch (\Throwable) {
            return 0;
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

        return null;
    }

    private function mapVoyage(object $voyage): array
    {
        // Fetch images from voyage_images table
        $images = $this->voyageImageRepository->findByVoyageId($voyage->getId());

        $imageUrls = array_map(function ($image) {
            // Handle both VoyageImage objects and plain arrays (from getDefaultImages)
            if (is_array($image)) {
                return $image['imageUrl'] ?? '';
            }
            return $image->getImageUrl();
        }, $images);

        return [
            'id' => $voyage->getId(),
            'title' => $voyage->getTitle(),
            'description' => $voyage->getDescription(),
            'destination' => $voyage->getDestination(),
            'start_date' => $voyage->getStartDate()?->format('Y-m-d'),
            'end_date' => $voyage->getEndDate()?->format('Y-m-d'),
            'price' => $voyage->getPrice(),
            'image_url' => $imageUrls,
        ];
    }
}
