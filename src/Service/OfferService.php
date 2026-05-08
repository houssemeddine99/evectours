<?php

namespace App\Service;

use App\Entity\Offer;
use App\Repository\OfferRepository;
use App\Repository\VoyageImageRepository;
use App\Repository\VoyageRepository;
use Doctrine\ORM\EntityManagerInterface;

use Psr\Log\LoggerInterface;

class OfferService
{
    private const DEFAULT_IMAGE = 'https://images.unsplash.com/photo-1488646953014-85cb44e25828?auto=format&fit=crop&w=1200&q=80';

    public function __construct(
        private readonly OfferRepository $offerRepository,
        private readonly VoyageRepository $voyageRepository,
        private readonly VoyageImageRepository $voyageImageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Create a new offer
     * @param array<mixed> $data
     */
    public function createOffer(array $data): ?Offer
    {
        $voyage = $this->voyageRepository->find($data['voyage_id'] ?? 0);
        if (!$voyage) {
            return null;
        }

        $offer = new Offer();
        $offer->setVoyage($voyage);
        $offer->setTitle($data['title'] ?? '');
        $offer->setDescription($data['description'] ?? null);
        $offer->setDiscountPercentage($data['discount_percentage'] ?? null);
        $offer->setStartDate(isset($data['start_date']) ? new \DateTime($data['start_date']) : null);
        $offer->setEndDate(isset($data['end_date']) ? new \DateTime($data['end_date']) : null);
        $offer->setIsActive($data['is_active'] ?? true);

        $this->entityManager->persist($offer);
        $this->entityManager->flush();

        return $offer;
    }

    /**
     * Update an existing offer
     * @param array<mixed> $data
     */
    public function updateOffer(int $id, array $data): ?Offer
    {
        $offer = $this->offerRepository->find($id);
        if (!$offer) {
            return null;
        }

        if (isset($data['voyage_id'])) {
            $voyage = $this->voyageRepository->find($data['voyage_id']);
            if ($voyage) {
                $offer->setVoyage($voyage);
            }
        }
        if (isset($data['title'])) {
            $offer->setTitle($data['title']);
        }
        if (isset($data['description'])) {
            $offer->setDescription($data['description']);
        }
        if (isset($data['discount_percentage'])) {
            $offer->setDiscountPercentage($data['discount_percentage']);
        }
        if (isset($data['start_date'])) {
            $offer->setStartDate(new \DateTime($data['start_date']));
        }
        if (isset($data['end_date'])) {
            $offer->setEndDate(new \DateTime($data['end_date']));
        }
        if (isset($data['is_active'])) {
            $offer->setIsActive($data['is_active']);
        }

        $this->entityManager->flush();

        return $offer;
    }

    /**
     * Delete an offer
     */
    public function deleteOffer(int $id): bool
    {
        $offer = $this->offerRepository->find($id);
        if (!$offer) {
            return false;
        }

        $this->entityManager->remove($offer);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Get all offers for admin
     * @return array<mixed>
     */
    public function getAllOffersForAdmin(): array
    {
        $offers = $this->safeExecute(fn () => $this->offerRepository->findAll(), []);

        return $this->normalizeOffers($offers, false);
    }

    /**
     * Get offer by ID for admin
     * @return array<mixed>
     */
    public function getOfferByIdForAdmin(int $id): ?array
    {
        $offer = $this->safeExecute(fn () => $this->offerRepository->find($id), null);

        if ($offer === null) {
            return null;
        }

        return $this->normalizeOffer($offer, false);
    }

    /** @return array<mixed> */
    public function getActiveOffers(): array
    {
        $offers = $this->safeExecute(fn () => $this->offerRepository->findActiveOffers(), []);

        return $this->normalizeOffers($offers, true);
    }

    /**
     * Normalize offers for output
     * @param Offer[] $offers
     * @return array
     * @return array<mixed>
     */
    private function normalizeOffers(array $offers, bool $includePriceAndImage): array
    {
        // Pre-batch image URLs to avoid N+1 (one query for all voyages)
        $imageMap = [];
        if ($includePriceAndImage) {
            $voyageIds = array_values(array_unique(array_filter(array_map(
                fn ($o) => $o->getVoyage()?->getId(),
                $offers
            ))));
            if (!empty($voyageIds)) {
                $preloaded = $this->voyageImageRepository->findImagesByVoyageIds($voyageIds);
                foreach ($voyageIds as $vid) {
                    $imgs = $preloaded[$vid] ?? [];
                    $imageMap[$vid] = !empty($imgs) ? $imgs[0]->getImageUrl() : self::DEFAULT_IMAGE;
                }
            }
        }

        $normalized = [];
        foreach ($offers as $offer) {
            $voyage = $offer->getVoyage();
            if ($voyage === null) {
                continue;
            }

            $data = [
                'id' => $offer->getId(),
                'title' => $offer->getTitle(),
                'description' => $offer->getDescription(),
                'discount_percentage' => (float) ($offer->getDiscountPercentage() ?? 0),
                'start_date' => $offer->getStartDate()?->format('Y-m-d'),
                'end_date' => $offer->getEndDate()?->format('Y-m-d'),
                'is_active' => $offer->isActive(),
                'voyage_id'    => $voyage->getId(),
                'voyage_slug'  => $voyage->getSlug(),
                'voyage_title' => $voyage->getTitle(),
                'destination'  => $voyage->getDestination(),
            ];

            if ($includePriceAndImage) {
                $data['price'] = (float) ($voyage->getPrice() ?? 0);
                $vid = $voyage->getId();
                $data['image_url'] = ($vid !== null ? ($imageMap[$vid] ?? null) : null) ?? self::DEFAULT_IMAGE;
            }

            // Days until expiry
            $endDate = $offer->getEndDate();
            $data['days_until_expiry'] = $endDate
                ? max(-1, (int) ceil(($endDate->getTimestamp() - time()) / 86400))
                : 999;

            // Flash sale
            $data['flash_sale_active']    = $offer->isFlashSaleActive();
            $data['flash_sale_ends_at']   = $offer->isFlashSaleActive() ? $offer->getFlashSaleEndsAt()?->format('c') : null;
            $data['flash_sale_discount']  = $offer->isFlashSaleActive() ? (float) ($offer->getFlashSaleDiscount() ?? 0) : 0;

            $normalized[] = $data;
        }

        return $normalized;
    }

    /**
     * Normalize a single offer for output
     * @return array<mixed>
     */
    private function normalizeOffer(Offer $offer, bool $includePriceAndImage): array
    {
        $voyage = $offer->getVoyage();

        $data = [
            'id' => $offer->getId(),
            'title' => $offer->getTitle(),
            'description' => $offer->getDescription(),
            'discount_percentage' => (float) ($offer->getDiscountPercentage() ?? 0),
            'start_date' => $offer->getStartDate()?->format('Y-m-d'),
            'end_date' => $offer->getEndDate()?->format('Y-m-d'),
            'is_active' => $offer->isActive(),
            'voyage_id' => $voyage?->getId(),
            'voyage_title' => $voyage?->getTitle(),
            'destination' => $voyage?->getDestination(),
        ];

        if ($includePriceAndImage && $voyage) {
            $data['price'] = (float) ($voyage->getPrice() ?? 0);
            $data['image_url'] = ($voyage->getImageUrl()[0] ?? null) ?? self::DEFAULT_IMAGE;
        }

        return $data;
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
            $this->logger?->error('OfferService error', ['error' => $e->getMessage()]);
            return $default;
        }
    }
}