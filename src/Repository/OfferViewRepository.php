<?php

namespace App\Repository;

use App\Entity\OfferView;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OfferView>
 */
class OfferViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OfferView::class);
    }

    /**
     * Find all views for a specific offer
     * @return OfferView[]
     */
    public function findByOfferId(int $offerId): array
    {
        return $this->createQueryBuilder('ov')
            ->andWhere('ov.offerId = :offerId')
            ->setParameter('offerId', $offerId)
            ->orderBy('ov.viewTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all views by a specific user
     * @return OfferView[]
     */
    public function findByUserId(int $userId): array
    {
        return $this->createQueryBuilder('ov')
            ->andWhere('ov.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('ov.viewTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find clicked views for a specific offer
     * @return OfferView[]
     */
    public function findClickedByOfferId(int $offerId): array
    {
        return $this->createQueryBuilder('ov')
            ->andWhere('ov.offerId = :offerId')
            ->andWhere('ov.clicked = :clicked')
            ->setParameter('offerId', $offerId)
            ->setParameter('clicked', true)
            ->orderBy('ov.viewTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count views for a specific offer
     */
    public function countByOfferId(int $offerId): int
    {
        return (int) $this->createQueryBuilder('ov')
            ->select('COUNT(ov.id)')
            ->andWhere('ov.offerId = :offerId')
            ->setParameter('offerId', $offerId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count clicked views for a specific offer
     */
    public function countClickedByOfferId(int $offerId): int
    {
        return (int) $this->createQueryBuilder('ov')
            ->select('COUNT(ov.id)')
            ->andWhere('ov.offerId = :offerId')
            ->andWhere('ov.clicked = :clicked')
            ->setParameter('offerId', $offerId)
            ->setParameter('clicked', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find recent views for an offer within a date range
     * @return OfferView[]
     */
    public function findRecentByOfferId(int $offerId, \DateTimeInterface $since): array
    {
        return $this->createQueryBuilder('ov')
            ->andWhere('ov.offerId = :offerId')
            ->andWhere('ov.viewTime >= :since')
            ->setParameter('offerId', $offerId)
            ->setParameter('since', $since)
            ->orderBy('ov.viewTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find unique users who viewed an offer
     * @return OfferView[]
     */
    public function findUniqueUsersByOfferId(int $offerId): array
    {
        return $this->createQueryBuilder('ov')
            ->andWhere('ov.offerId = :offerId')
            ->setParameter('offerId', $offerId)
            ->groupBy('ov.userId')
            ->orderBy('MAX(ov.viewTime)', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get click-through rate for an offer
     */
    public function getClickThroughRate(int $offerId): float
    {
        $totalViews = $this->countByOfferId($offerId);
        if ($totalViews === 0) {
            return 0.0;
        }
        
        $clickedViews = $this->countClickedByOfferId($offerId);
        return ($clickedViews / $totalViews) * 100;
    }

    /**
     * Find most viewed offers
     * @return array<int, array{offerId: mixed, viewCount: mixed}>
     */
    public function findMostViewedOffers(int $limit = 10): array
    {
        return $this->createQueryBuilder('ov')
            ->select('ov.offerId, COUNT(ov.id) as viewCount')
            ->groupBy('ov.offerId')
            ->orderBy('viewCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find offers viewed by a user that haven't been clicked
     * @return OfferView[]
     */
    public function findViewedButNotClickedByUser(int $userId): array
    {
        return $this->createQueryBuilder('ov')
            ->andWhere('ov.userId = :userId')
            ->andWhere('ov.clicked = :clicked')
            ->setParameter('userId', $userId)
            ->setParameter('clicked', false)
            ->orderBy('ov.viewTime', 'DESC')
            ->getQuery()
            ->getResult();
    }
}