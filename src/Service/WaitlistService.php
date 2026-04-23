<?php

namespace App\Service;

use App\Entity\WaitlistEntry;
use App\Repository\WaitlistEntryRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class WaitlistService
{
    private const HIGH_DEMAND_THRESHOLD = 10;

    public function __construct(
        private readonly WaitlistEntryRepository $waitlistRepository,
        private readonly ReservationRepository $reservationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    public function isHighDemand(int $voyageId): bool
    {
        return $this->countActiveReservations($voyageId) >= self::HIGH_DEMAND_THRESHOLD;
    }

    public function getActiveReservationCount(int $voyageId): int
    {
        return $this->countActiveReservations($voyageId);
    }

    public function isOnWaitlist(int $userId, int $voyageId): bool
    {
        return $this->waitlistRepository->findByUserAndVoyage($userId, $voyageId) !== null;
    }

    public function getPosition(int $userId, int $voyageId): ?int
    {
        $entries = $this->waitlistRepository->findByVoyageId($voyageId);
        foreach ($entries as $i => $entry) {
            if ($entry->getUserId() === $userId) {
                return $i + 1;
            }
        }
        return null;
    }

    public function join(int $userId, int $voyageId): bool
    {
        if ($this->isOnWaitlist($userId, $voyageId)) {
            return true;
        }
        try {
            $entry = new WaitlistEntry();
            $entry->setUserId($userId);
            $entry->setVoyageId($voyageId);
            $this->entityManager->persist($entry);
            $this->entityManager->flush();
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('WaitlistService: join failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function leave(int $userId, int $voyageId): bool
    {
        $entry = $this->waitlistRepository->findByUserAndVoyage($userId, $voyageId);
        if (!$entry) {
            return false;
        }
        try {
            $this->entityManager->remove($entry);
            $this->entityManager->flush();
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('WaitlistService: leave failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /** Returns the next unnotified waitlist entry for a voyage, or null. */
    public function getNextEntry(int $voyageId): ?WaitlistEntry
    {
        $entries = $this->waitlistRepository->findByVoyageId($voyageId);
        return $entries[0] ?? null;
    }

    public function markNotified(int $entryId): void
    {
        $entry = $this->waitlistRepository->find($entryId);
        if ($entry) {
            $entry->setNotified(true);
            $this->entityManager->flush();
        }
    }

    private function countActiveReservations(int $voyageId): int
    {
        $all = $this->reservationRepository->findByVoyageId($voyageId);
        return count(array_filter($all, fn($r) => in_array($r->getStatus(), ['PENDING', 'CONFIRMED'], true)));
    }
}
