<?php

namespace App\Repository;

use App\Entity\Reclamation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reclamation>
 */
class ReclamationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reclamation::class);
    }

    /**
     * Find all reclamations for a user
     * @return Reclamation[]
     */
    public function findByUserId(int $userId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('r.reclamationDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find reclamations by status
     * @return Reclamation[]
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->setParameter('status', $status)
            ->orderBy('r.reclamationDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find reclamations by priority
     * @return Reclamation[]
     */
    public function findByPriority(string $priority): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.priority = :priority')
            ->setParameter('priority', $priority)
            ->orderBy('r.reclamationDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find open reclamations
     * @return Reclamation[]
     */
    public function findOpenReclamations(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->setParameter('status', 'OPEN')
            ->orderBy('r.priority', 'DESC')
            ->addOrderBy('r.reclamationDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
// src/Repository/ReclamationRepository.php

public function findPaginated(int $page, int $limit, ?int $userId = null): array
{
    $qb = $this->createQueryBuilder('r')
        ->orderBy('r.priority', 'DESC')
        ->addOrderBy('r.reclamationDate', 'ASC');

    // ONLY apply the filter if $userId is not null
    if ($userId !== null) {
        $qb->andWhere('r.userId = :userId')
           ->setParameter('userId', $userId);
    }

    // Clone for the total count
    $countQb = clone $qb;
    $totalItems = (int) $countQb->select('COUNT(r.id)')
        ->resetDQLPart('orderBy') 
        ->getQuery()
        ->getSingleScalarResult();

    // Fetch the data slice
    $results = $qb->setFirstResult(($page - 1) * $limit)
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();

    return [
        'data' => $results,
        'totalItems' => $totalItems,
        'totalPages' => $totalItems > 0 ? (int) ceil($totalItems / $limit) : 1,
        'currentPage' => $page,
        'limit' => $limit
    ];
}
    /**
     * Find urgent reclamations
     * @return Reclamation[]
     */
    public function findUrgentReclamations(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.priority = :priority')
            ->setParameter('priority', 'URGENT')
            ->andWhere('r.status != :closed')
            ->setParameter('closed', 'CLOSED')
            ->orderBy('r.reclamationDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count open reclamations
     */
    public function countOpenReclamations(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.status = :status')
            ->setParameter('status', 'OPEN')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find reclamations by reservation
     * @return Reclamation[]
     */
    public function findByReservationId(int $reservationId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.reservationId = :reservationId')
            ->setParameter('reservationId', $reservationId)
            ->orderBy('r.reclamationDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent reclamations
     * @return Reclamation[]
     */
    public function findRecentReclamations(int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.reclamationDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}