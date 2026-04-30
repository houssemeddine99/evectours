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
     */
    public function getAllVoyagesForAdmin(): array
    {
        $voyages = $this->safeExecute(fn () => $this->voyageRepository->findAllOrdered());
        $ids = array_map(fn ($v) => $v->getId(), $voyages);
        $preloaded = $this->safeExecute(fn () => $this->voyageImageRepository->findImagesByVoyageIds($ids), []);

        return array_map(fn ($voyage) => $this->mapVoyageForAdmin($voyage, $preloaded), $voyages);
    }

    /**
     * Get voyage by ID for admin
     */
    public function getVoyageByIdForAdmin(int $id): ?array
    {
        $voyage = $this->safeExecute(fn () => $this->voyageRepository->find($id));

        if ($voyage !== null) {
            return $this->mapVoyageForAdmin($voyage);
        }

        return null;
    }

    private function mapVoyageForAdmin(object $voyage, ?array $preloadedImages = null): array
    {
        if ($preloadedImages !== null) {
            $imgs = $preloadedImages[$voyage->getId()] ?? [];
            $imageUrls = array_map(fn ($img) => $img->getImageUrl(), $imgs)
                ?: ['https://cratertravelagencies.com/assets/img/crater5.jpg'];
        } else {
            $imageUrls = $this->extractImageUrls($voyage->getId());
        }
        $tags = [];
        foreach ($voyage->getTags() as $tag) {
            $tags[] = ['id' => $tag->getId(), 'name' => $tag->getName(), 'color' => $tag->getColor()];
        }

        $slug = $voyage->getSlug();
        if ($slug === '') {
            $slug = 'voyage-' . $voyage->getId();
        }

        return [
            'id' => $voyage->getId(),
            'slug' => $slug,
            'title' => $voyage->getTitle(),
            'description' => $voyage->getDescription(),
            'destination' => $voyage->getDestination(),
            'start_date' => $voyage->getStartDate()?->format('Y-m-d'),
            'end_date' => $voyage->getEndDate()?->format('Y-m-d'),
            'price' => $voyage->getPrice(),
            'image_url' => $imageUrls,
            'created_at' => $voyage->getCreatedAt()?->format('Y-m-d H:i:s'),
            'activities_count' => $voyage->getActivities()->count(),
            'offers_count' => $voyage->getOffers()->count(),
            'tags' => $tags,
        ];
    }

    public function getFeaturedVoyages(int $limit = 3): array
    {
        $voyages = $this->safeExecute(fn () => $this->voyageRepository->findFeatured($limit));
        $ids = array_map(fn ($v) => $v->getId(), $voyages);
        $preloaded = $this->safeExecute(fn () => $this->voyageImageRepository->findImagesByVoyageIds($ids), []);

        return array_map(fn ($voyage) => $this->mapVoyage($voyage, $preloaded), $voyages);
    }

    public function getAllVoyages(): array
    {
        $voyages = $this->safeExecute(fn () => $this->voyageRepository->findAllOrdered());
        $ids = array_map(fn ($v) => $v->getId(), $voyages);
        $preloaded = $this->safeExecute(fn () => $this->voyageImageRepository->findImagesByVoyageIds($ids), []);

        return array_map(fn ($voyage) => $this->mapVoyage($voyage, $preloaded), $voyages);
    }

    /**
     * Returns a slim summary of active voyages for AI prompts (no images, capped at $limit rows).
     * @return array<array{id:int,title:string,destination:string,price:float,start_date:string,end_date:string}>
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

    public function getVoyages(int $page = 1, int $limit = 12): array
    {
        $voyages = $this->safeExecute(fn () => $this->voyageRepository->findPublicPaginated($limit, ($page - 1) * $limit));
        $ids = array_map(fn ($v) => $v->getId(), $voyages);
        $preloaded = $this->safeExecute(fn () => $this->voyageImageRepository->findImagesByVoyageIds($ids), []);

        return array_map(fn ($voyage) => $this->mapVoyage($voyage, $preloaded), $voyages);
    }

    public function getTotalVoyages(): int
    {
        return $this->safeExecute(fn () => $this->voyageRepository->countPublic(), 0);
    }

    public function getAllActiveVoyages(): array
    {
        $voyages = $this->safeExecute(fn () => $this->voyageRepository->findAllActive());
        $ids = array_map(fn ($v) => $v->getId(), $voyages);
        $preloaded = $this->safeExecute(fn () => $this->voyageImageRepository->findImagesByVoyageIds($ids), []);

        return array_map(fn ($voyage) => $this->mapVoyage($voyage, $preloaded), $voyages);
    }

    public function getVoyageById(int $id): ?array
    {
        $voyage = $this->safeExecute(fn () => $this->voyageRepository->find($id));

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

    private function mapVoyage(object $voyage, ?array $preloadedImages = null): array
    {
        $tags = [];
        foreach ($voyage->getTags() as $tag) {
            $tags[] = ['id' => $tag->getId(), 'name' => $tag->getName(), 'color' => $tag->getColor()];
        }

        $slug = $voyage->getSlug();
        if ($slug === '') {
            $slug = 'voyage-' . $voyage->getId();
        }

        if ($preloadedImages !== null) {
            $imgs = $preloadedImages[$voyage->getId()] ?? [];
            $imageUrls = array_map(fn ($img) => $img->getImageUrl(), $imgs)
                ?: ['https://cratertravelagencies.com/assets/img/crater5.jpg'];
        } else {
            $imageUrls = $this->extractImageUrls($voyage->getId());
        }

        $basePrice = (float) ($voyage->getPrice() ?? 0);
        $pricing   = $this->dynamicPricingService->calculate($basePrice, $voyage->getId(), $voyage->getStartDate());

        return [
            'id'              => $voyage->getId(),
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
            'tags'            => $tags,
        ];
    }

    public function getVoyageBySlug(string $slug): ?array
    {
        $voyage = $this->safeExecute(fn () => $this->voyageRepository->findBySlug($slug));

        // Fallback: if slug is the "voyage-{id}" pattern and not found by slug, try by ID
        if ($voyage === null && preg_match('/^voyage-(\d+)$/', $slug, $m)) {
            $voyage = $this->safeExecute(fn () => $this->voyageRepository->find((int) $m[1]));
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
    public function searchVoyages(array $filters): array
    {
        $this->logger?->info('Searching voyages with filters', $filters);
        $voyages = $this->safeExecute(fn () => $this->voyageRepository->search($filters));
        $ids = array_map(fn ($v) => $v->getId(), $voyages);
        $preloaded = $this->safeExecute(fn () => $this->voyageImageRepository->findImagesByVoyageIds($ids), []);

        return array_map(fn ($voyage) => $this->mapVoyage($voyage, $preloaded), $voyages);
    }

    /**
     * Count search results
     */
    public function countSearchResults(array $filters): int
    {
        return $this->safeExecute(fn () => $this->voyageRepository->countSearch($filters), 0);
    }
}
