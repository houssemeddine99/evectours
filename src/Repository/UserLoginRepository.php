<?php

namespace App\Repository;

use App\Entity\UserLogin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserLogin>
 */
class UserLoginRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserLogin::class);
    }

    /**
     * Find all logins by a specific user
     * @return UserLogin[]
     */
    public function findByUserId(int $userId): array
    {
        return $this->createQueryBuilder('ul')
            ->andWhere('ul.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('ul.loginTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent logins for a user
     * @return UserLogin[]
     */
    public function findRecentByUserId(int $userId, int $limit = 10): array
    {
        return $this->createQueryBuilder('ul')
            ->andWhere('ul.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('ul.loginTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find logins by method
     * @return UserLogin[]
     */
    public function findByLoginMethod(string $loginMethod): array
    {
        return $this->createQueryBuilder('ul')
            ->andWhere('ul.loginMethod = :loginMethod')
            ->setParameter('loginMethod', $loginMethod)
            ->orderBy('ul.loginTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find logins from a specific IP
     * @return UserLogin[]
     */
    public function findByIpAddress(string $ipAddress): array
    {
        return $this->createQueryBuilder('ul')
            ->andWhere('ul.ipAddress = :ipAddress')
            ->setParameter('ipAddress', $ipAddress)
            ->orderBy('ul.loginTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count logins by a user
     */
    public function countByUserId(int $userId): int
    {
        return (int) $this->createQueryBuilder('ul')
            ->select('COUNT(ul.id)')
            ->andWhere('ul.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get user's last login
     */
    public function findLastLoginByUserId(int $userId): ?UserLogin
    {
        return $this->createQueryBuilder('ul')
            ->andWhere('ul.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('ul.loginTime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find logins within a date range
     * @return UserLogin[]
     */
    public function findLoginsBetween(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('ul')
            ->andWhere('ul.loginTime >= :start')
            ->andWhere('ul.loginTime <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('ul.loginTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get login statistics
     * @return array
     */
    public function getLoginStatistics(): array
    {
        return $this->createQueryBuilder('ul')
            ->select('ul.loginMethod, COUNT(ul.id) as loginCount')
            ->groupBy('ul.loginMethod')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find suspicious logins (different IPs in short time)
     * @return UserLogin[]
     */
    public function findSuspiciousLogins(int $userId, int $hours = 24): array
    {
        $since = new \DateTime("-{$hours} hours");
        
        return $this->createQueryBuilder('ul')
            ->andWhere('ul.userId = :userId')
            ->andWhere('ul.loginTime >= :since')
            ->setParameter('userId', $userId)
            ->setParameter('since', $since)
            ->orderBy('ul.loginTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Delete old login records
     */
    public function deleteOldLogins(\DateTimeInterface $before): int
    {
        return $this->createQueryBuilder('ul')
            ->delete()
            ->andWhere('ul.loginTime < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}