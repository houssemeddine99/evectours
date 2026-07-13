<?php

namespace App\Repository;

use App\Entity\HotelBooking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HotelBooking>
 */
class HotelBookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HotelBooking::class);
    }

    public function save(HotelBooking $booking, bool $flush = true): void
    {
        $this->getEntityManager()->persist($booking);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /** @return HotelBooking[] */
    public function findForUser(int $userId): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.userId = :uid')->setParameter('uid', $userId)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()->getResult();
    }
}
