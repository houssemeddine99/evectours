<?php

namespace App\Service;

use App\Entity\SearchHistory;
use App\Repository\SearchHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SearchHistoryService
{
    public function __construct(
        private readonly SearchHistoryRepository $searchHistoryRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Record a search
     */
    public function recordSearch(int $userId, string $query, string $type, int $resultsFound = 0): SearchHistory
    {
        $searchHistory = new SearchHistory();
        $searchHistory->setUserId($userId);
        $searchHistory->setSearchQuery($query);
        $searchHistory->setSearchType($type);
        $searchHistory->setSearchTime(new \DateTime());
        $searchHistory->setResultsFound($resultsFound);

        $this->entityManager->persist($searchHistory);
        $this->entityManager->flush();

        return $searchHistory;
    }

    /**
     * Get user's search history
     */
    public function getUserSearchHistory(int $userId): array
    {
        return $this->safeExecute(fn () => $this->searchHistoryRepository->findByUserId($userId), []);
    }

    /**
     * Get recent searches for a user
     */
    public function getRecentSearches(int $userId, int $limit = 10): array
    {
        return $this->safeExecute(fn () => $this->searchHistoryRepository->findRecentByUserId($userId, $limit), []);
    }

    /**
     * Get most popular search queries
     */
    public function getPopularQueries(int $limit = 10): array
    {
        return $this->safeExecute(fn () => $this->searchHistoryRepository->findMostPopularQueries($limit), []);
    }

    /**
     * Safely execute a callback with error handling
     */
    private function safeExecute(callable $callback, mixed $default = []): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            $this->logger?->error('SearchHistoryService error', ['error' => $e->getMessage()]);
            return $default;
        }
    }
}