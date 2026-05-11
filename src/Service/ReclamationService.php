<?php

namespace App\Service;

use App\Entity\Reclamation;
use App\Repository\ReclamationRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\AuthService;
use Psr\Log\LoggerInterface;

class ReclamationService
{
    public function __construct(
        private readonly ReclamationRepository $reclamationRepository,
        private readonly ReservationRepository $reservationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthService $authService,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * @return array{eligible: bool, reason?: string}
     */
    public function evaluateRefundEligibility(int $reclamationId, int $userId): array
    {
        $reclamation = $this->reclamationRepository->find($reclamationId);
        if (!$reclamation || $reclamation->getUserId() !== $userId) {
            return ['eligible' => false, 'reason' => 'Reclamation not found for this user.'];
        }

        $reservationId = $reclamation->getReservationId();
        if ($reservationId <= 0) {
            return ['eligible' => false, 'reason' => 'Reclamation has no valid reservation.'];
        }

        $reservation = $this->reservationRepository->find($reservationId);
        if (!$reservation || $reservation->getUserId() !== $userId) {
            return ['eligible' => false, 'reason' => 'Reservation does not belong to this user.'];
        }

        $reservationStatus = strtoupper((string) $reservation->getStatus());
        if (!in_array($reservationStatus, ['CONFIRMED', 'COMPLETED', 'CANCELLED', 'PENDING'], true)) {
            return ['eligible' => false, 'reason' => 'Reservation status is not eligible for a refund.'];
        }

        try {
            $pendingCount = (int) $this->entityManager->createQueryBuilder()
                ->select('COUNT(rr.id)')
                ->from('App\\Entity\\RefundRequest', 'rr')
                ->andWhere('rr.reclamationId = :reclamationId')
                ->andWhere('rr.status = :status')
                ->setParameter('reclamationId', $reclamationId)
                ->setParameter('status', 'PENDING')
                ->getQuery()
                ->getSingleScalarResult();

            if ($pendingCount > 0) {
                return ['eligible' => false, 'reason' => 'A pending refund request already exists for this reclamation.'];
            }
        } catch (NoResultException) {
            // No pending requests found.
        }

        return ['eligible' => true];
    }

    /**
     * Create a new reclamation
     * @param array<mixed> $data
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
        $reclamation->setResponseDeadline($this->computeDeadline($data['priority'] ?? 'MEDIUM'));

        $this->entityManager->persist($reclamation);
        $this->entityManager->flush();

        return $reclamation;
    }

    public static function slaHours(string $priority): int
    {
        return match (strtoupper($priority)) {
            'URGENT' => 4,
            'HIGH'   => 24,
            'LOW'    => 120,
            default  => 72,
        };
    }

    private function computeDeadline(string $priority): \DateTime
    {
        $hours = self::slaHours($priority);
        return (new \DateTime())->modify("+{$hours} hours");
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
/** @return array<mixed> */
public function getPaginatedReclamations(int $page, int $limit, ?string $email = null): array
{
    return $this->safeExecute(function() use ($page, $limit, $email) {
        $userId = null;
        
        // If an email is provided, try to find the user ID
        if ($email) {
            $user = $this->authService->getUserByEmail($email); // Assuming this exists
            $userId = $user ? $user['id'] : -1; // -1 ensures no results if user not found
        }

        return $this->reclamationRepository->findPaginated($page, $limit, $userId);
    }, [
        'data' => [], 'totalItems' => 0, 'totalPages' => 0, 
        'currentPage' => $page, 'limit' => $limit
    ]);
}
    /**
     * Get all open reclamations
     * @return array<mixed>
     */
    public function getOpenReclamations(): array
    {
        return $this->safeExecute(fn () => $this->reclamationRepository->findOpenReclamations(), []);
    }

    /**
     * Get urgent reclamations
     * @return array<mixed>
     */
    public function getUrgentReclamations(): array
    {
        return $this->safeExecute(fn () => $this->reclamationRepository->findUrgentReclamations(), []);
    }

    /**
     * Get reclamations by user
     * @return array<mixed>
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