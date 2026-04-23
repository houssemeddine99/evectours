<?php

namespace App\Repository;

use App\Entity\Favorite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Favorite>
 */
class FavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Favorite::class);
    }

    public function findByUserAndVoyage(int $userId, int $voyageId): ?Favorite
    {
        return $this->findOneBy(['userId' => $userId, 'voyageId' => $voyageId]);
    }

    /** @return int[] */
    public function findVoyageIdsByUser(int $userId): array
    {
        $results = $this->createQueryBuilder('f')
            ->select('f.voyageId')
            ->where('f.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'voyageId');
    }
}
