<?php

namespace App\Repository;

use App\Entity\Association;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Association>
 */
class AssociationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Association::class);
    }

    /**
     * Find association by company code
     */
    public function findByCompanyCode(string $companyCode): ?Association
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.companyCode = :companyCode')
            ->setParameter('companyCode', $companyCode)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all associations ordered by name
     * @return Association[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find associations with discount
     * @return Association[]
     */
    public function findWithDiscount(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.discountRate > 0')
            ->orderBy('a.discountRate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search associations by name
     * @return Association[]
     */
    public function searchByName(string $name): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.name LIKE :name')
            ->setParameter('name', '%' . $name . '%')
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}