<?php

namespace App\Service;

use App\Entity\UserAssociation;
use App\Repository\UserAssociationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class UserAssociationService
{
    public function __construct(
        private readonly UserAssociationRepository $userAssociationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Assign association to user
     */
    public function assignAssociation(int $userId, int $associationId): UserAssociation
    {
        $userAssociation = $this->userAssociationRepository->findByUserId($userId) ?? new UserAssociation();
        
        $userAssociation->setUserId($userId);
        $userAssociation->setAssociationId($associationId);

        $this->entityManager->persist($userAssociation);
        $this->entityManager->flush();

        return $userAssociation;
    }

    /**
     * Get user's association
     */
    public function getUserAssociation(int $userId): ?UserAssociation
    {
        return $this->safeExecute(fn () => $this->userAssociationRepository->findByUserId($userId));
    }

    /**
     * Get users by association
     * @return array<mixed>
     */
    public function getUsersByAssociation(int $associationId): array
    {
        return $this->safeExecute(fn () => $this->userAssociationRepository->findByAssociationId($associationId), []);
    }

    /**
     * Safely execute a callback with error handling
     */
    private function safeExecute(callable $callback, mixed $default = []): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            $this->logger?->error('UserAssociationService error', ['error' => $e->getMessage()]);
            return $default;
        }
    }
}