<?php

namespace App\Repository;

use App\Entity\Voyage;
use App\Entity\VoyageVisit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VoyageVisit>
 */
class VoyageVisitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VoyageVisit::class);
    }

    /**
     * Find all visits for a specific voyage
     * @return VoyageVisit[]
     */
    public function findByVoyageId(int $voyageId): array
    {
        return $this->createQueryBuilder('vv')
            ->andWhere('vv.voyageId = :voyageId')
            ->setParameter('voyageId', $voyageId)
            ->orderBy('vv.visitTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all visits by a specific user
     * @return VoyageVisit[]
     */
    public function findByUserId(int $userId): array
    {
        return $this->createQueryBuilder('vv')
            ->andWhere('vv.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('vv.visitTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent visits for a user
     * @return VoyageVisit[]
     */
    public function findRecentByUserId(int $userId, int $limit = 10): array
    {
        return $this->createQueryBuilder('vv')
            ->andWhere('vv.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('vv.visitTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find visits by source
     * @return VoyageVisit[]
     */
    public function findBySource(string $source): array
    {
        return $this->createQueryBuilder('vv')
            ->andWhere('vv.source = :source')
            ->setParameter('source', $source)
            ->orderBy('vv.visitTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count visits for a specific voyage
     */
    public function countByVoyageId(int $voyageId): int
    {
        return (int) $this->createQueryBuilder('vv')
            ->select('COUNT(vv.id)')
            ->andWhere('vv.voyageId = :voyageId')
            ->setParameter('voyageId', $voyageId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get total view duration for a voyage
     */
    public function getTotalViewDuration(int $voyageId): int
    {
        return (int) $this->createQueryBuilder('vv')
            ->select('SUM(vv.viewDurationSeconds)')
            ->andWhere('vv.voyageId = :voyageId')
            ->setParameter('voyageId', $voyageId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get average view duration for a voyage
     */
    public function getAverageViewDuration(int $voyageId): float
    {
        $result = $this->createQueryBuilder('vv')
            ->select('AVG(vv.viewDurationSeconds)')
            ->andWhere('vv.voyageId = :voyageId')
            ->setParameter('voyageId', $voyageId)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (float) $result : 0.0;
    }

    /**
     * Find most visited voyages
     * @return array
     */
    public function findMostVisitedVoyages(int $limit = 10): array
    {
        return $this->createQueryBuilder('vv')
            ->select('vv.voyageId, COUNT(vv.id) as visitCount, SUM(vv.viewDurationSeconds) as totalDuration')
            ->groupBy('vv.voyageId')
            ->orderBy('visitCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find unique users who visited a voyage
     * @return VoyageVisit[]
     */
    public function findUniqueUsersByVoyageId(int $voyageId): array
    {
        return $this->createQueryBuilder('vv')
            ->andWhere('vv.voyageId = :voyageId')
            ->setParameter('voyageId', $voyageId)
            ->groupBy('vv.userId')
            ->orderBy('MAX(vv.visitTime)', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find visits within a date range
     * @return VoyageVisit[]
     */
    public function findVisitsBetween(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('vv')
            ->andWhere('vv.visitTime >= :start')
            ->andWhere('vv.visitTime <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('vv.visitTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find long-view visits (users who viewed for more than X seconds)
     * @return VoyageVisit[]
     */
    public function findLongViewVisits(int $voyageId, int $minSeconds = 60): array
    {
        return $this->createQueryBuilder('vv')
            ->andWhere('vv.voyageId = :voyageId')
            ->andWhere('vv.viewDurationSeconds >= :minSeconds')
            ->setParameter('voyageId', $voyageId)
            ->setParameter('minSeconds', $minSeconds)
            ->orderBy('vv.viewDurationSeconds', 'DESC')
            ->getQuery()
            ->getResult();
    }
    // Add/Update these methods in VoyageVisitRepository.php

/**
 * Find most visited voyages with their titles
 */
/**
 * Find most visited voyages with their titles
 */
public function findMostVisitedVoyagesWithNames(int $limit = 10): array
{
    return $this->createQueryBuilder('vv')
        // Use v.title (from Voyage entity) instead of titre
        ->select('v.title as voyageName, vv.voyageId, COUNT(vv.id) as visitCount')
        ->join('App\Entity\Voyage', 'v', 'WITH', 'vv.voyageId = v.id')
        ->groupBy('vv.voyageId, v.title')
        ->orderBy('visitCount', 'DESC')
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
}

/**
 * Get paginated visits with voyage titles
 */
public function findPaginatedWithNames(int $offset, int $limit): array
{
    return $this->createQueryBuilder('vv')
        ->select('vv', 'v.title as voyageName')
        ->join('App\Entity\Voyage', 'v', 'WITH', 'vv.voyageId = v.id')
        ->orderBy('vv.visitTime', 'DESC')
        ->setFirstResult($offset)
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
}

public function getSourceBreakdown(): array
{
    return $this->createQueryBuilder('vv')
        ->select('vv.source, COUNT(vv.id) as cnt')
        ->groupBy('vv.source')
        ->orderBy('cnt', 'DESC')
        ->getQuery()
        ->getResult();
}

public function getVisitsByDay(int $days = 30): array
{
    $conn = $this->getEntityManager()->getConnection();
    $since = (new \DateTime("-{$days} days"))->format('Y-m-d');
    $sql = 'SELECT DATE(visit_time) as day, COUNT(id) as cnt FROM voyage_visits WHERE visit_time >= :since GROUP BY DATE(visit_time) ORDER BY day ASC';
    return $conn->executeQuery($sql, ['since' => $since])->fetchAllAssociative();
}

public function getTotalVisits(): int
{
    return (int) $this->createQueryBuilder('vv')
        ->select('COUNT(vv.id)')
        ->getQuery()
        ->getSingleScalarResult();
}
}