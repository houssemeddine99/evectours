<?php

namespace App\Repository;

use App\Entity\Review;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Review> */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    /** @return Review[] */
    public function findByVoyageId(int $voyageId): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.voyageId = :vid')
            ->setParameter('vid', $voyageId)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByUserAndVoyage(int $userId, int $voyageId): ?Review
    {
        return $this->findOneBy(['userId' => $userId, 'voyageId' => $voyageId]);
    }

    public function getAverageRating(int $voyageId): ?float
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(r.rating) as avg_rating')
            ->where('r.voyageId = :vid')
            ->setParameter('vid', $voyageId)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? round((float) $result, 1) : null;
    }

    public function countByVoyageId(int $voyageId): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.voyageId = :vid')
            ->setParameter('vid', $voyageId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
