<?php

namespace App\Repository;

use App\Entity\UserAssociation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserAssociation>
 */
class UserAssociationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserAssociation::class);
    }

    /**
     * Find association by user ID
     */
    public function findByUserId(int $userId): ?UserAssociation
    {
        return $this->createQueryBuilder('ua')
            ->andWhere('ua.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find users by association ID
     * @return UserAssociation[]
     */
    public function findByAssociationId(int $associationId): array
    {
        return $this->createQueryBuilder('ua')
            ->andWhere('ua.associationId = :associationId')
            ->setParameter('associationId', $associationId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find users with associations
     * @return UserAssociation[]
     */
    public function findUsersWithAssociations(): array
    {
        return $this->createQueryBuilder('ua')
            ->andWhere('ua.associationId IS NOT NULL')
            ->getQuery()
            ->getResult();
    }
}