<?php

namespace App\Service;

use App\Entity\UserOffer;
use App\Repository\UserOfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class UserOfferService
{
    public function __construct(
        private readonly UserOfferRepository $userOfferRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Claim an offer for a user
     */
    public function claimOffer(int $userId, int $offerId): ?UserOffer
    {
        // Check if already claimed
        if ($this->userOfferRepository->hasUserClaimedOffer($userId, $offerId)) {
            return null;
        }

        $userOffer = new UserOffer();
        $userOffer->setUserId($userId);
        $userOffer->setOfferId($offerId);
        $userOffer->setClaimedAt(new \DateTime());
        $userOffer->setStatus('ACTIVE');

        $this->entityManager->persist($userOffer);
        $this->entityManager->flush();

        return $userOffer;
    }

    /**
     * Mark offer as used
     */
    public function markAsUsed(int $userId, int $offerId): bool
    {
        $userOffers = $this->userOfferRepository->findByUserId($userId);
        
        foreach ($userOffers as $userOffer) {
            if ($userOffer->getOfferId() === $offerId && $userOffer->getStatus() === 'ACTIVE') {
                $userOffer->setStatus('USED');
                $this->entityManager->flush();
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get active offers for a user
     */
    public function getActiveOffers(int $userId): array
    {
        return $this->safeExecute(fn () => $this->userOfferRepository->findActiveByUserId($userId), []);
    }

    /**
     * Get all offers for a user
     */
    public function getOffersByUser(int $userId): array
    {
        return $this->safeExecute(fn () => $this->userOfferRepository->findByUserId($userId), []);
    }

    /**
     * Check if user has claimed offer
     */
    public function hasUserClaimedOffer(int $userId, int $offerId): bool
    {
        return $this->safeExecute(fn () => $this->userOfferRepository->hasUserClaimedOffer($userId, $offerId), false);
    }

    /**
     * Count active offers for user
     */
    public function countActiveOffers(int $userId): int
    {
        return $this->safeExecute(fn () => $this->userOfferRepository->countActiveByUserId($userId), 0);
    }

    /**
     * Safely execute a callback with error handling
     */
    private function safeExecute(callable $callback, mixed $default = []): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            $this->logger?->error('UserOfferService error', ['error' => $e->getMessage()]);
            return $default;
        }
    }
}