<?php

namespace App\Repository;

use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * Find all reservations for a user
     * @return Reservation[]
     */
    public function findByUserId(int $userId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('r.reservationDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all reservations for a voyage
     * @return Reservation[]
     */
    public function findByVoyageId(int $voyageId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.voyageId = :voyageId')
            ->setParameter('voyageId', $voyageId)
            ->orderBy('r.reservationDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find reservations by status
     * @return Reservation[]
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->setParameter('status', $status)
            ->orderBy('r.reservationDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find reservations by payment status
     * @return Reservation[]
     */
    public function findByPaymentStatus(string $paymentStatus): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.paymentStatus = :paymentStatus')
            ->setParameter('paymentStatus', $paymentStatus)
            ->orderBy('r.reservationDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find pending reservations
     * @return Reservation[]
     */
    public function findPendingReservations(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->setParameter('status', 'PENDING')
            ->orderBy('r.reservationDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find confirmed reservations for a user
     * @return Reservation[]
     */
    public function findConfirmedByUserId(int $userId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.userId = :userId')
            ->andWhere('r.status = :status')
            ->setParameter('userId', $userId)
            ->setParameter('status', 'CONFIRMED')
            ->orderBy('r.reservationDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count reservations by user
     */
    public function countByUserId(int $userId): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumBookedPeopleByVoyageId(int $voyageId): int
    {
        $result = $this->createQueryBuilder('r')
            ->select('SUM(r.numberOfPeople)')
            ->where('r.voyageId = :vid')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('vid', $voyageId)
            ->setParameter('statuses', ['CONFIRMED', 'PENDING'])
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Get total revenue from confirmed reservations
     */
    public function getTotalRevenue(): float
    {
        $result = $this->createQueryBuilder('r')
            ->select('SUM(r.totalPrice)')
            ->andWhere('r.paymentStatus = :paymentStatus')
            ->setParameter('paymentStatus', 'PAID')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (float) $result : 0.0;
    }

    /**
     * Find reservations within date range
     * @return Reservation[]
     */
    public function findReservationsBetween(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.reservationDate >= :start')
            ->andWhere('r.reservationDate <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('r.reservationDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find user's reservation for a specific voyage
     */
    public function findUserVoyageReservation(int $userId, int $voyageId): ?Reservation
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.userId = :userId')
            ->andWhere('r.voyageId = :voyageId')
            ->setParameter('userId', $userId)
            ->setParameter('voyageId', $voyageId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}