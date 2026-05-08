<?php

namespace App\Repository;

use App\Entity\WaitlistEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WaitlistEntry>
 */
class WaitlistEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WaitlistEntry::class);
    }

    /** @return array<mixed> */
    public function findByVoyageId(int $voyageId): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.voyageId = :voyageId')
            ->andWhere('w.notified = false')
            ->setParameter('voyageId', $voyageId)
            ->orderBy('w.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByUserAndVoyage(int $userId, int $voyageId): ?WaitlistEntry
    {
        return $this->findOneBy(['userId' => $userId, 'voyageId' => $voyageId]);
    }
}
