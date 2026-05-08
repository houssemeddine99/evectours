<?php

namespace App\Service;

use App\Entity\VoyageVisit;
use App\Repository\VoyageVisitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class VoyageVisitService
{
    public function __construct(
        private readonly VoyageVisitRepository $voyageVisitRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Record a voyage visit
     */
    public function recordVisit(int $userId, int $voyageId, string $source = 'direct'): VoyageVisit
    {
        $voyageVisit = new VoyageVisit();
        $voyageVisit->setUserId($userId);
        $voyageVisit->setVoyageId($voyageId);
        $voyageVisit->setVisitTime(new \DateTime());
        $voyageVisit->setSource($source);
        $voyageVisit->setViewDurationSeconds(0);

        $this->entityManager->persist($voyageVisit);
        $this->entityManager->flush();

        return $voyageVisit;
    }

    /**
     * Update view duration
     */
    public function updateViewDuration(int $id, int $durationSeconds): ?VoyageVisit
    {
        $voyageVisit = $this->voyageVisitRepository->find($id);
        if (!$voyageVisit) {
            return null;
        }

        $voyageVisit->setViewDurationSeconds($durationSeconds);
        $this->entityManager->flush();

        return $voyageVisit;
    }

    /**
     * Get visits for a voyage
     */
    /** @return array<mixed> */
    public function getVisitsByVoyage(int $voyageId): array
    {
        return $this->safeExecute(fn() => $this->voyageVisitRepository->findByVoyageId($voyageId), []);
    }
    /** @return array<string, mixed> */
    public function getPaginatedVisits(int $page = 1, int $limit = 50): array
    {
        $offset = ($page - 1) * $limit;

        return $this->safeExecute(function () use ($limit, $offset, $page) {
            $results = $this->voyageVisitRepository->findPaginatedWithNames($offset, $limit);
            $totalItems = $this->voyageVisitRepository->count([]);

            return [
                'data' => $results,
                'totalItems' => $totalItems,
                'currentPage' => $page,
                'totalPages' => ceil($totalItems / $limit)
            ];
        }, ['data' => [], 'totalItems' => 0, 'currentPage' => 1, 'totalPages' => 1]);
    }
    /**
     * Get user's visited voyages
     */
    /** @return array<mixed> */
    public function getUserVisits(int $userId): array
    {
        return $this->safeExecute(fn() => $this->voyageVisitRepository->findByUserId($userId), []);
    }

    /**
     * Get most visited voyages
     */
    /** @return array<mixed> */
    public function getMostVisitedVoyages(int $limit = 10): array
    {
        return $this->safeExecute(fn() => $this->voyageVisitRepository->findMostVisitedVoyagesWithNames($limit), []);
    }

    /**
     * Get average view duration for a voyage
     */
    public function getAverageViewDuration(int $voyageId): float
    {
        return $this->safeExecute(fn() => $this->voyageVisitRepository->getAverageViewDuration($voyageId), 0.0);
    }

    /**
     * Safely execute a callback with error handling
     */
    private function safeExecute(callable $callback, mixed $default = []): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            $this->logger?->error('VoyageVisitService error', ['error' => $e->getMessage()]);
            return $default;
        }
    }
}
