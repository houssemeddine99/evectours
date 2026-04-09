<?php

namespace App\Repository;

use App\Entity\Offer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Offer>
 */
class OfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Offer::class);
    }

    /** @return Offer[] */
    public function findActiveOffers(): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.isActive = :active')
            ->andWhere('o.endDate >= :today')
            ->setParameter('active', true)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('o.discountPercentage', 'DESC')
            ->addOrderBy('o.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
public function findByVoyageId(int $voyageId, bool $onlyActive = true): array
{
    $qb = $this->createQueryBuilder('o')
        ->andWhere('o.voyage = :voyageId')
        ->setParameter('voyageId', $voyageId);
    
    if ($onlyActive) {
        $qb->andWhere('o.isActive = :active')
           ->setParameter('active', true);
    }
    
    return $qb->getQuery()->getResult();
}
}
