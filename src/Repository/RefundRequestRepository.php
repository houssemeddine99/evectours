<?php

namespace App\Repository;

use App\Entity\RefundRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefundRequest>
 */
class RefundRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefundRequest::class);
    }

    /**
     * Find all requests by requester
     * @return RefundRequest[]
     */
    public function findByRequesterId(int $requesterId): array
    {
        return $this->createQueryBuilder('rr')
            ->andWhere('rr.requesterId = :requesterId')
            ->setParameter('requesterId', $requesterId)
            ->orderBy('rr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find requests by status
     * @return RefundRequest[]
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('rr')
            ->andWhere('rr.status = :status')
            ->setParameter('status', $status)
            ->orderBy('rr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find pending requests
     * @return RefundRequest[]
     */
    public function findPendingRequests(): array
    {
        return $this->createQueryBuilder('rr')
            ->andWhere('rr.status = :status')
            ->setParameter('status', 'PENDING')
            ->orderBy('rr.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find requests by reclamation
     * @return RefundRequest[]
     */
    public function findByReclamationId(int $reclamationId): array
    {
        return $this->createQueryBuilder('rr')
            ->andWhere('rr.reclamationId = :reclamationId')
            ->setParameter('reclamationId', $reclamationId)
            ->orderBy('rr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count pending requests
     */
    public function countPendingRequests(): int
    {
        return (int) $this->createQueryBuilder('rr')
            ->select('COUNT(rr.id)')
            ->andWhere('rr.status = :status')
            ->setParameter('status', 'PENDING')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get total refund amount pending
     */
    public function getTotalPendingAmount(): float
    {
        $result = $this->createQueryBuilder('rr')
            ->select('SUM(rr.amount)')
            ->andWhere('rr.status = :status')
            ->setParameter('status', 'PENDING')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (float) $result : 0.0;
    }
}