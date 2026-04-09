<?php

namespace App\Repository;

use App\Entity\UserOffer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserOffer>
 */
class UserOfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserOffer::class);
    }

    /**
     * Find all offers claimed by a user
     * @return UserOffer[]
     */
    public function findByUserId(int $userId): array
    {
        return $this->createQueryBuilder('uo')
            ->andWhere('uo.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('uo.claimedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active offers for a user
     * @return UserOffer[]
     */
    public function findActiveByUserId(int $userId): array
    {
        return $this->createQueryBuilder('uo')
            ->andWhere('uo.userId = :userId')
            ->andWhere('uo.status = :status')
            ->setParameter('userId', $userId)
            ->setParameter('status', 'ACTIVE')
            ->orderBy('uo.claimedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find users who claimed a specific offer
     * @return UserOffer[]
     */
    public function findByOfferId(int $offerId): array
    {
        return $this->createQueryBuilder('uo')
            ->andWhere('uo.offerId = :offerId')
            ->setParameter('offerId', $offerId)
            ->orderBy('uo.claimedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if user has already claimed an offer
     */
    public function hasUserClaimedOffer(int $userId, int $offerId): bool
    {
        return $this->createQueryBuilder('uo')
            ->andWhere('uo.userId = :userId')
            ->andWhere('uo.offerId = :offerId')
            ->setParameter('userId', $userId)
            ->setParameter('offerId', $offerId)
            ->getQuery()
            ->getOneOrNullResult() !== null;
    }

    /**
     * Count active offers for a user
     */
    public function countActiveByUserId(int $userId): int
    {
        return (int) $this->createQueryBuilder('uo')
            ->select('COUNT(uo.id)')
            ->andWhere('uo.userId = :userId')
            ->andWhere('uo.status = :status')
            ->setParameter('userId', $userId)
            ->setParameter('status', 'ACTIVE')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find expired offers for a user
     * @return UserOffer[]
     */
    public function findExpiredByUserId(int $userId): array
    {
        return $this->createQueryBuilder('uo')
            ->andWhere('uo.userId = :userId')
            ->andWhere('uo.status = :status')
            ->setParameter('userId', $userId)
            ->setParameter('status', 'EXPIRED')
            ->orderBy('uo.claimedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}