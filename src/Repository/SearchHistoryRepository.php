<?php

namespace App\Repository;

use App\Entity\SearchHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SearchHistory>
 */
class SearchHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SearchHistory::class);
    }

    /**
     * Find all searches by a specific user
     * @return SearchHistory[]
     */
    public function findByUserId(int $userId): array
    {
        return $this->createQueryBuilder('sh')
            ->andWhere('sh.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('sh.searchTime', 'DESC')
            ->getQuery()
            ->getResult();
    }
    /**
 * Find paginated search history for admin
 * @return array{data: SearchHistory[], totalItems: int, totalPages: int, currentPage: int, limit: int}
 */
public function findPaginated(int $page, int $limit): array
{
    $queryBuilder = $this->createQueryBuilder('sh')
        ->orderBy('sh.searchTime', 'DESC');

    // 1. Get total count
$countQueryBuilder = clone $queryBuilder;
$totalItems = (int) $countQueryBuilder
    ->select('COUNT(sh.id)')
    ->resetDQLPart('orderBy') // Add this line!
    ->getQuery()
    ->getSingleScalarResult();

    // 2. Get paginated results
    $results = $queryBuilder
        ->setFirstResult(($page - 1) * $limit)
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();

    $totalPages = (int) ceil($totalItems / $limit);

    return [
        'data' => $results,
        'totalItems' => $totalItems,
        'totalPages' => $totalPages,
        'currentPage' => $page,
        'limit' => $limit
    ];
}

    /**
     * Find searches by type
     * @return SearchHistory[]
     */
    public function findBySearchType(string $searchType): array
    {
        return $this->createQueryBuilder('sh')
            ->andWhere('sh.searchType = :searchType')
            ->setParameter('searchType', $searchType)
            ->orderBy('sh.searchTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent searches for a user
     * @return SearchHistory[]
     */
    public function findRecentByUserId(int $userId, int $limit = 10): array
    {
        return $this->createQueryBuilder('sh')
            ->andWhere('sh.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('sh.searchTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find searches containing a specific query
     * @return SearchHistory[]
     */
    public function findByQuery(string $query): array
    {
        return $this->createQueryBuilder('sh')
            ->andWhere('sh.searchQuery LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('sh.searchTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count searches by a user
     */
    public function countByUserId(int $userId): int
    {
        return (int) $this->createQueryBuilder('sh')
            ->select('COUNT(sh.id)')
            ->andWhere('sh.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get most popular search queries
     * @return array<int, array{searchQuery: mixed, searchCount: mixed}>
     */
    public function findMostPopularQueries(int $limit = 10): array
    {
        return $this->createQueryBuilder('sh')
            ->select('sh.searchQuery, COUNT(sh.id) as searchCount')
            ->groupBy('sh.searchQuery')
            ->orderBy('searchCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get search analytics by type
     * @return array<int, array{searchType: mixed, totalSearches: mixed, totalResults: mixed}>
     */
    public function getSearchAnalyticsByType(): array
    {
        return $this->createQueryBuilder('sh')
            ->select('sh.searchType, COUNT(sh.id) as totalSearches, SUM(sh.resultsFound) as totalResults')
            ->groupBy('sh.searchType')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find searches with no results
     * @return SearchHistory[]
     */
    public function findNoResultsSearches(): array
    {
        return $this->createQueryBuilder('sh')
            ->andWhere('sh.resultsFound = 0')
            ->orderBy('sh.searchTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Delete old search history for a user
     */
    public function deleteOldSearches(int $userId, \DateTimeInterface $before): int
    {
        return $this->createQueryBuilder('sh')
            ->delete()
            ->andWhere('sh.userId = :userId')
            ->andWhere('sh.searchTime < :before')
            ->setParameter('userId', $userId)
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}