<?php

namespace App\Service;

use App\Entity\Reclamation;
use App\Repository\ReclamationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ReclamationService
{
    public function __construct(
        private readonly ReclamationRepository $reclamationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Create a new reclamation
     */
    public function createReclamation(array $data): Reclamation
    {
        $reclamation = new Reclamation();
        $reclamation->setReservationId($data['reservation_id'] ?? 0);
        $reclamation->setUserId($data['user_id'] ?? 0);
        $reclamation->setTitle($data['title'] ?? '');
        $reclamation->setDescription($data['description'] ?? '');
        $reclamation->setStatus($data['status'] ?? 'OPEN');
        $reclamation->setPriority($data['priority'] ?? 'MEDIUM');
        $reclamation->setReclamationDate(new \DateTime());
        $reclamation->setCreatedAt(new \DateTime());
        $reclamation->setUpdatedAt(new \DateTime());

        $this->entityManager->persist($reclamation);
        $this->entityManager->flush();

        return $reclamation;
    }

    /**
     * Update reclamation status
     */
    public function updateStatus(int $id, string $status): ?Reclamation
    {
        $reclamation = $this->reclamationRepository->find($id);
        if (!$reclamation) {
            return null;
        }

        $reclamation->setStatus($status);
        $reclamation->setUpdatedAt(new \DateTime());

        if ($status === 'RESOLVED' || $status === 'CLOSED') {
            $reclamation->setResolutionDate(new \DateTime());
        }

        $this->entityManager->flush();
        return $reclamation;
    }

    /**
     * Add admin response to reclamation
     */
    public function addResponse(int $id, string $response): ?Reclamation
    {
        $reclamation = $this->reclamationRepository->find($id);
        if (!$reclamation) {
            return null;
        }

        $reclamation->setAdminResponse($response);
        $reclamation->setResponseDate(new \DateTime());
        $reclamation->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();
        return $reclamation;
    }

    /**
     * Get all open reclamations
     */
    public function getOpenReclamations(): array
    {
        return $this->safeExecute(fn () => $this->reclamationRepository->findOpenReclamations(), []);
    }

    /**
     * Get urgent reclamations
     */
    public function getUrgentReclamations(): array
    {
        return $this->safeExecute(fn () => $this->reclamationRepository->findUrgentReclamations(), []);
    }

    /**
     * Get reclamations by user
     */
    public function getReclamationsByUser(int $userId): array
    {
        return $this->safeExecute(fn () => $this->reclamationRepository->findByUserId($userId), []);
    }

    /**
     * Get reclamation by ID
     */
    public function getReclamationById(int $id): ?Reclamation
    {
        return $this->safeExecute(fn () => $this->reclamationRepository->find($id));
    }

    /**
     * Count open reclamations
     */
    public function countOpenReclamations(): int
    {
        return $this->safeExecute(fn () => $this->reclamationRepository->countOpenReclamations(), 0);
    }

    /**
     * Safely execute a callback with error handling
     */
    private function safeExecute(callable $callback, mixed $default = []): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            $this->logger?->error('ReclamationService error', ['error' => $e->getMessage()]);
            return $default;
        }
    }
}