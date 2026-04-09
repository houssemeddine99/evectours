<?php

namespace App\Repository;

use App\Entity\Admin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Admin>
 */
class AdminRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Admin::class);
    }

    /**
     * Find admin by user ID
     */
    public function findByUserId(int $userId): ?Admin
    {
        return $this->createQueryBuilder('a')
            ->andWhere('IDENTITY(a.user) = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all admins ordered by access level
     * @return Admin[]
     */
    public function findAllOrderedByAccessLevel(): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.accessLevel', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find admins by access level
     * @return Admin[]
     */
    public function findByAccessLevel(int $accessLevel): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.accessLevel = :accessLevel')
            ->setParameter('accessLevel', $accessLevel)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find super admins (highest access level)
     * @return Admin[]
     */
    public function findSuperAdmins(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.accessLevel >= :minLevel')
            ->setParameter('minLevel', 5)
            ->getQuery()
            ->getResult();
    }
}
