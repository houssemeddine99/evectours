<?php

namespace App\Service;

use App\Entity\Voyage;
use App\Repository\VoyageImageRepository;
use App\Repository\VoyageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class VoyageService
{
    public function __construct(
        private readonly VoyageRepository $voyageRepository,
        private readonly VoyageImageRepository $voyageImageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly DynamicPricingService $dynamicPricingService,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Create a new voyage
     * @param array<mixed> $data
     */
    public function createVoyage(array $data): Voyage
    {
        $voyage = new Voyage();
        $voyage->setTitle($data['title'] ?? '');
        $voyage->setDescription($data['description'] ?? null);
        $voyage->setDestination($data['destination'] ?? '');
        $voyage->setStartDate(isset($data['start_date']) ? new \DateTime($data['start_date']) : null);
        $voyage->setEndDate(isset($data['end_date']) ? new \DateTime($data['end_date']) : null);
        $voyage->setPrice($data['price'] ?? null);
       // $voyage->setImageUrl($data['image_url'] ?? []);
        $voyage->setCreatedAt(new \DateTime());

        $this->entityManager->persist($voyage);
        $this->entityManager->flush();

        return $voyage;
    }

    /**
     * Update an existing voyage
     * @param array<mixed> $data
     */
    public function updateVoyage(int $id, array $data): ?Voyage
    {
        $voyage = $this->voyageRepository->find($id);
        if (!$voyage) {
            return null;
        }

        if (isset($data['title'])) {
            $voyage->setTitle($data['title']);
        }
        if (isset($data['description'])) {
            $voyage->setDescription($data['description']);
        }
        if (isset($data['destination'])) {
            $voyage->setDestination($data['destination']);
        }
        if (isset($data['start_date'])) {
            $voyage->setStartDate(new \DateTime($data['start_date']));
        }
        if (isset($data['end_date'])) {
            $voyage->setEndDate(new \DateTime($data['end_date']));
        }
        if (isset($data['price'])) {
            $voyage->setPrice($data['price']);
        }
     

        $this->entityManager->flush();

        return $voyage;
    }

    /**
     * Delete a voyage
     */
    public function deleteVoyage(int $id): bool
    {
        $voyage = $this->voyageRepository->find($id);
        if (!$voyage) {
            return false;
        }

        $this->entityManager->remove($voyage);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Get all voyages for admin
     * @return array<mixed>
     */
    public function getAllVoyagesForAdmin(): array
    {
        $voyages = $this->safeExecute(fn () => $this->voyageRepository->findAllOrdered());
        $ids = array_values(array_filter(array_map(fn ($v) => $v->getId(), $voyages)));
        $preloaded = $this->safeExecute(fn () => $this->voyageImageRepository->findImagesByVoyageIds($ids), []);
        $actCounts = $this->safeExecute(fn () => $this->voyageRepository->countActivitiesByVoyageIds($ids), []);
        $offerCounts = $this->safeExecute(fn () => $this->voyageRepository->countOffersByVoyageIds($ids), []);

        return array_map(fn ($voyage) => $this->mapVoyageForAdmin($voyage, $preloaded, $actCounts, $offerCounts), $voyages);
    }

    /**
     * Get voyage by ID for admin
     * @return array<mixed>
     */
    public function getVoyageByIdForAdmin(int $id): ?array
    {
        $voyage = $this->safeExecute(fn () => $this->voyageRepository->find($id), null);

        if ($voyage !== null) {
            return $this->mapVoyageForAdmin($voyage);
        }

        return null;
    }

    /**
     * @param array<mixed>|null $preloadedImages
     * @param array<mixed>|null $actCounts
     * @param array<mixed>|null $offerCounts
     * @return array<string, mixed>
     */
    private function mapVoyageForAdmin(Voyage $voyage, ?array $preloadedImages = null, ?array $actCounts = null, ?array $offerCounts = null): array
    {
        $vid = (int) $voyage->getId();
        if ($preloadedImages !== null) {
            $imgs = $preloadedImages[$vid] ?? [];
            $imageUrls = array_map(fn ($img) => $img->getImageUrl(), $imgs)
                ?: ['https://cratertravelagencies.com/assets/img/crater5.jpg'];
        } else {
            $imageUrls = $this->extractImageUrls($vid);
        }
        $slug = $voyage->getSlug();
        if ($slug === '') {
            $slug = 'voyage-' . $vid;
        }

        return [
            'id' => $vid,
            'slug' => $slug,
            'title' => $voyage->getTitle(),
            'description' => $voyage->getDescription(),
            'destination' => $voyage->getDestination(),
            'start_date' => $voyage->getStartDate()?->format('Y-m-d'),
            'end_date' => $voyage->getEndDate()?->format('Y-m-d'),
            'price' => $voyage->getPrice(),
            'image_url' => $imageUrls,
            'created_at' => $voyage->getCreatedAt()?->format('Y-m-d H:i:s'),
            'activities_count' => $actCounts !== null ? ($actCounts[$vid] ?? 0) : $voyage->getActivities()->count(),
            'offers_count' => $offerCounts !== null ? ($offerCounts[$vid] ?? 0) : $voyage->getOffers()->count(),
        ];
    }

    /** @return array<mixed> */
    public function getFeaturedVoyages(int $limit = 3): array
    {
        $voyages = $this->safeExecute(fn () => $this->voyageRepository->findFeatured($limit));
        $ids = array_values(array_filter(array_map(fn ($v) => $v->getId(), $voyages)));
        $preloaded = $this->safeExecute(fn () => $this->voyageImageRepository->findImagesByVoyageIds($ids), []);
        $bookedMap = $this->safeExecute(fn () => $this->dynamicPricingService->preloadBookedCounts($ids), []);

        return array_map(fn ($voyage) => $this->mapVoyage($voyage, $preloaded, $bookedMap), $voyages);
    }

    /** @return array<mixed> */
    public function getAllVoyages(): array
    {
        $voyages = $this->safeExecute(fn () => $this->voyageRepository->findAllOrdered());
        $ids = array_values(array_filter(array_map(fn ($v) => $v->getId(), $voyages)));
        $preloaded = $this->safeExecute(fn () => $this->voyageImageRepository->findImagesByVoyageIds($ids), []);
        $bookedMap = $this->safeExecute(fn () => $this->dynamicPricingService->preloadBookedCounts($ids), []);

        return array_map(fn ($voyage) => $this->mapVoyage($voyage, $preloaded, $bookedMap), $voyages);
    }

    /**
     * Returns a slim summary of active voyages for AI prompts (no images, capped at $limit rows).
     * @return array<array<string, mixed>>
     */
    public function getSlimVoyagesForAi(int $limit = 50): array
    {
        return $this->safeExecute(function () use ($limit) {
            return $this->entityManager->getConnection()->fetchAllAssociative(
                'SELECT id, title, destination, price, start_date, end_date
                 FROM voyages
                 ORDER BY id DESC
                 LIMIT ' . $limit
            );
        }, []);
    }

    /** @return array<mixed> */
    public function getVoyages(int $page = 1, int $limit = 12): array
    {
        $voyages = $this->safeExecute(fn () => $this->voyageRepository->findPublicPaginated($limit, ($page - 1) * $limit));
        $ids = array_values(array_filter(array_map(fn ($v) => $v->getId(), $voyages)));
        $preloaded = $this->safeExecute(fn () => $this->voyageImageRepository->findImagesByVoyageIds($ids), []);
        $bookedMap = $this->safeExecute(fn () => $this->dynamicPricingService->preloadBookedCounts($ids), []);

        return array_map(fn ($voyage) => $this->mapVoyage($voyage, $preloaded, $bookedMap), $voyages);
    }

    public function getTotalVoyages(): int
    {
        return $this->safeExecute(fn () => $this->voyageRepository->countPublic(), 0);
    }

    /** @return array<mixed> */
    public function getAllActiveVoyages(): array
    {
        $voyages = $this->safeExecute(fn () => $this->voyageRepository->findAllActive());
        $ids = array_values(array_filter(array_map(fn ($v) => $v->getId(), $voyages)));
        $preloaded = $this->safeExecute(fn () => $this->voyageImageRepository->findImagesByVoyageIds($ids), []);
        $bookedMap = $this->safeExecute(fn () => $this->dynamicPricingService->preloadBookedCounts($ids), []);

        return array_map(fn ($voyage) => $this->mapVoyage($voyage, $preloaded, $bookedMap), $voyages);
    }

    /**
     * Batch-load voyages by IDs. Returns map of id => voyage array.
     * @param int[] $ids
     * @return array<int, array>
     * @return array<mixed>
     */
    public function getVoyagesByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $voyages  = $this->safeExecute(fn () => $this->voyageRepository->findByIds($ids), []);
        $preloaded = $this->safeExecute(fn () => $this->voyageImageRepository->findImagesByVoyageIds($ids), []);
        $bookedMap = $this->safeExecute(fn () => $this->dynamicPricingService->preloadBookedCounts($ids), []);

        $result = [];
        foreach ($voyages as $voyage) {
            $vid = $voyage->getId();
            if ($vid !== null) {
                $result[$vid] = $this->mapVoyage($voyage, $preloaded, $bookedMap);
            }
        }
        return $result;
    }

    /** @return array<mixed> */
    public function getVoyageById(int $id): ?array
    {
        $voyage = $this->safeExecute(fn () => $this->voyageRepository->find($id), null);

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

    /**
     * @param array<mixed>|null $preloadedImages
     * @param array<mixed>|null $bookedMap
     * @return array<string, mixed>
     */
    private function mapVoyage(Voyage $voyage, ?array $preloadedImages = null, ?array $bookedMap = null): array
    {
        $vid = (int) $voyage->getId();
        $slug = $voyage->getSlug();
        if ($slug === '') {
            $slug = 'voyage-' . $vid;
        }

        if ($preloadedImages !== null) {
            $imgs = $preloadedImages[$vid] ?? [];
            $imageUrls = array_map(fn ($img) => $img->getImageUrl(), $imgs)
                ?: ['https://cratertravelagencies.com/assets/img/crater5.jpg'];
        } else {
            $imageUrls = $this->extractImageUrls($vid);
        }

        $basePrice = (float) ($voyage->getPrice() ?? 0);
        $pricing   = $bookedMap !== null
            ? $this->dynamicPricingService->calculateWithBooked($basePrice, $vid, $voyage->getStartDate(), $bookedMap)
            : $this->dynamicPricingService->calculate($basePrice, $vid, $voyage->getStartDate());

        return [
            'id'              => $vid,
            'slug'            => $slug,
            'title'           => $voyage->getTitle(),
            'description'     => $voyage->getDescription(),
            'destination'     => $voyage->getDestination(),
            'start_date'      => $voyage->getStartDate()?->format('Y-m-d'),
            'end_date'        => $voyage->getEndDate()?->format('Y-m-d'),
            'price'           => (string) $pricing['price'],
            'base_price'      => (string) $pricing['base_price'],
            'scarcity_label'  => $pricing['scarcity_label'],
            'scarcity_level'  => $pricing['scarcity_level'],
            'booked_people'   => $pricing['booked'],
            'image_url'       => $imageUrls,
        ];
    }

    /** @return array<string, mixed>|null */
    public function getVoyageBySlug(string $slug): ?array
    {
        $voyage = $this->safeExecute(fn () => $this->voyageRepository->findBySlug($slug), null);

        // Fallback: if slug is the "voyage-{id}" pattern and not found by slug, try by ID
        if ($voyage === null && preg_match('/^voyage-(\d+)$/', $slug, $m)) {
            $voyage = $this->safeExecute(fn () => $this->voyageRepository->find((int) $m[1]), null);
        }

        if ($voyage === null) {
            return null;
        }
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

    /**
     * Extract image URLs from voyage images repository
     * @return string[]
     */
    public function extractImageUrls(int $voyageId): array
    {
        $images = $this->safeExecute(fn () => $this->voyageImageRepository->findByVoyageId($voyageId), []);

        return array_map(fn ($image) => $image->getImageUrl(), $images);
    }

    /**
     * Safely execute a callback with error handling
     * @template T
     * @param callable(): T $callback
     * @param T $default
     * @return T
     */
    private function safeExecute(callable $callback, mixed $default = []): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            $this->logger?->error('Service error', ['error' => $e->getMessage()]);
            return $default;
        }
    }

    /**
     * Search voyages with filters
     */
    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function searchVoyages(array $filters): array
    {
        $this->logger?->info('Searching voyages with filters', $filters);
        $voyages = $this->safeExecute(fn () => $this->voyageRepository->search($filters));
        $ids = array_values(array_filter(array_map(fn ($v) => $v->getId(), $voyages)));
        $preloaded = $this->safeExecute(fn () => $this->voyageImageRepository->findImagesByVoyageIds($ids), []);
        $bookedMap = $this->safeExecute(fn () => $this->dynamicPricingService->preloadBookedCounts($ids), []);

        return array_map(fn ($voyage) => $this->mapVoyage($voyage, $preloaded, $bookedMap), $voyages);
    }

    /**
     * Count search results
     */
    /** @param array<string, mixed> $filters */
    public function countSearchResults(array $filters): int
    {
        return $this->safeExecute(fn () => $this->voyageRepository->countSearch($filters), 0);
    }
}
