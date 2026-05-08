<?php

namespace App\Repository;

use App\Entity\UserDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserDocument>
 */
class UserDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserDocument::class);
    }

    /**
     * Find document by user ID
     * @return UserDocument[]
     */
    public function findByUserId(int $userId): array
    {
        return $this->createQueryBuilder('ud')
            ->andWhere('ud.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find document by passport number
     */
    public function findByPassportNumber(string $passportNumber): ?UserDocument
    {
        return $this->createQueryBuilder('ud')
            ->andWhere('ud.passportNumber = :passportNumber')
            ->setParameter('passportNumber', $passportNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find document by CIN number
     */
    public function findByCinNumber(string $cinNumber): ?UserDocument
    {
        return $this->createQueryBuilder('ud')
            ->andWhere('ud.cinNumber = :cinNumber')
            ->setParameter('cinNumber', $cinNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find documents with expired passport
     * @return UserDocument[]
     */
    public function findExpiredPassports(): array
    {
        return $this->createQueryBuilder('ud')
            ->andWhere('ud.passportExpiryDate < :today')
            ->setParameter('today', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * Find documents with upcoming passport expiry (within 6 months)
     * @return UserDocument[]
     */
    public function findExpiringPassports(int $months = 6): array
    {
        $futureDate = new \DateTime("+{$months} months");
        
        return $this->createQueryBuilder('ud')
            ->andWhere('ud.passportExpiryDate <= :futureDate')
            ->andWhere('ud.passportExpiryDate >= :today')
            ->setParameter('futureDate', $futureDate)
            ->setParameter('today', new \DateTime())
            ->getQuery()
            ->getResult();
    }
}