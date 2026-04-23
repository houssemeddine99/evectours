<?php

namespace App\Service;

use App\Entity\LoyaltyPoints;
use App\Repository\LoyaltyPointsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class LoyaltyPointsService
{
    /** @var array<int, LoyaltyPoints> */
    private array $cache = [];

    public function __construct(
        private readonly LoyaltyPointsRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    public function getBalance(int $userId): int
    {
        return $this->getOrCreate($userId)->getPointsBalance();
    }

    /** Award 1 point per TND/unit spent. Called when reservation is confirmed. */
    public function awardPoints(int $userId, float $amount): void
    {
        $points = (int) floor($amount);
        if ($points <= 0) {
            return;
        }

        try {
            $record = $this->getOrCreate($userId);
            $record->setPointsBalance($record->getPointsBalance() + $points);
            $record->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();
            $this->logger->info('LoyaltyPoints: awarded', ['user_id' => $userId, 'points' => $points]);
        } catch (\Throwable $e) {
            $this->logger->error('LoyaltyPoints: awardPoints failed', ['error' => $e->getMessage()]);
        }
    }

    /** Returns true and deducts 100 points if the user has enough for a discount. */
    public function redeemDiscount(int $userId): bool
    {
        try {
            $record = $this->getOrCreate($userId);
            if ($record->getPointsBalance() < 100) {
                return false;
            }
            $record->setPointsBalance($record->getPointsBalance() - 100);
            $record->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('LoyaltyPoints: redeemDiscount failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function canRedeem(int $userId): bool
    {
        return $this->getBalance($userId) >= 100;
    }

    private function getOrCreate(int $userId): LoyaltyPoints
    {
        if (isset($this->cache[$userId])) {
            return $this->cache[$userId];
        }
        $record = $this->repository->findByUserId($userId);
        if ($record === null) {
            $record = new LoyaltyPoints();
            $record->setUserId($userId);
            $this->entityManager->persist($record);
        }
        $this->cache[$userId] = $record;
        return $record;
    }
}
