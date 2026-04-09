<?php

namespace App\Service;

use App\Entity\OfferView;
use App\Repository\OfferViewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class OfferViewService
{
    public function __construct(
        private readonly OfferViewRepository $offerViewRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Record an offer view
     */
    public function recordView(int $userId, int $offerId, bool $clicked = false): OfferView
    {
        $offerView = new OfferView();
        $offerView->setUserId($userId);
        $offerView->setOfferId($offerId);
        $offerView->setViewTime(new \DateTime());
        $offerView->setClicked($clicked);

        $this->entityManager->persist($offerView);
        $this->entityManager->flush();

        return $offerView;
    }

    /**
     * Mark view as clicked
     */
    public function markAsClicked(int $id): ?OfferView
    {
        $offerView = $this->offerViewRepository->find($id);
        if (!$offerView) {
            return null;
        }

        $offerView->setClicked(true);
        $this->entityManager->flush();

        return $offerView;
    }

    /**
     * Get views for an offer
     */
    public function getViewsByOffer(int $offerId): array
    {
        return $this->safeExecute(fn () => $this->offerViewRepository->findByOfferId($offerId), []);
    }

    /**
     * Get click-through rate for an offer
     */
    public function getClickThroughRate(int $offerId): float
    {
        return $this->safeExecute(fn () => $this->offerViewRepository->getClickThroughRate($offerId), 0.0);
    }

    /**
     * Get most viewed offers
     */
    public function getMostViewedOffers(int $limit = 10): array
    {
        return $this->safeExecute(fn () => $this->offerViewRepository->findMostViewedOffers($limit), []);
    }

    /**
     * Safely execute a callback with error handling
     */
    private function safeExecute(callable $callback, mixed $default = []): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            $this->logger?->error('OfferViewService error', ['error' => $e->getMessage()]);
            return $default;
        }
    }
}