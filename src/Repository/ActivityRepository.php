<?php

namespace App\Repository;

use App\Entity\Activity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Activity>
 */
class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }
    /** @return Activity[] */
    public function findByVoyageId(int $voyageId): array
{
    return $this->createQueryBuilder('a')
        ->andWhere('a.voyage = :voyage')
        ->setParameter('voyage', $voyageId) // Doctrine handles ID to entity
        ->getQuery()
        ->getResult();
}
}
