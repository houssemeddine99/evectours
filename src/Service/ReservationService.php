<?php

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\RefundRequest;
use App\Entity\Reclamation;
use App\Repository\ReservationRepository;
use App\Service\RefundRequestService;
use App\Service\LoyaltyPointsService;
use App\Repository\VoyageRepository;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;

class ReservationService
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly VoyageRepository $voyageRepository,
        private readonly UserRepository $userRepository,
        private readonly RefundRequestService $refundRequestService,
        private readonly LoyaltyPointsService $loyaltyPointsService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array{eligible: bool, reason?: string}
     */
    public function evaluateRefundEligibility(int $reservationId, int $userId): array
    {
        $reservation = $this->reservationRepository->find($reservationId);
        if (!$reservation || $reservation->getUserId() !== $userId) {
            return ['eligible' => false, 'reason' => 'Reservation not found for this user.'];
        }

        $status = strtoupper((string) $reservation->getStatus());
        if (!in_array($status, ['CONFIRMED', 'COMPLETED', 'CANCELLED'], true)) {
            return ['eligible' => false, 'reason' => 'Reservation status is not refundable.'];
        }

        $paymentStatus = strtoupper((string) $reservation->getPaymentStatus());
        if ($paymentStatus === 'REFUNDED') {
            return ['eligible' => false, 'reason' => 'Reservation is already refunded.'];
        }

        if ($paymentStatus !== 'PAID') {
            return ['eligible' => false, 'reason' => 'Only paid reservations are refundable.'];
        }

        $pendingCount = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(rr.id)')
            ->from(RefundRequest::class, 'rr')
            ->join(Reclamation::class, 'r', 'WITH', 'rr.reclamationId = r.id')
            ->andWhere('rr.requesterId = :userId')
            ->andWhere('rr.status = :status')
            ->andWhere('r.reservationId = :reservationId')
            ->setParameter('userId', $userId)
            ->setParameter('status', 'PENDING')
            ->setParameter('reservationId', $reservationId)
            ->getQuery()
            ->getSingleScalarResult();

        if ($pendingCount > 0) {
            return ['eligible' => false, 'reason' => 'A pending refund request already exists for this reservation.'];
        }

        return ['eligible' => true];
    }

    public function createReservation(int $userId, int $voyageId, ?int $offerId, int $numberOfPeople, float $totalPrice): ?array
    {
        if ($numberOfPeople <= 0 || $totalPrice < 0) {
            return null;
        }

        try {
            // Check if user exists
            $user = $this->userRepository->find($userId);
            if (!$user) {
                $this->logger->warning('User not found for reservation', ['user_id' => $userId]);
                return null;
            }

            // Check if voyage exists
            $voyage = $this->voyageRepository->find($voyageId);
            if (!$voyage) {
                $this->logger->warning('Voyage not found for reservation', ['voyage_id' => $voyageId]);
                return null;
            }

            // Keep unique constraint stable: if a pending/cancelled reservation already exists, return it instead of failing.
            $existing = $this->getReservationByUserAndVoyage($userId, $voyageId);
            if ($existing) {
                $this->logger->info('Existing reservation found for user/voyage; returning existing', ['user_id' => $userId, 'voyage_id' => $voyageId, 'status' => $existing['status']]);
                return $existing;
            }

            // Create new reservation using Entity
            $reservation = new Reservation();
            $reservation->setUserId($userId);
            $reservation->setVoyageId($voyageId);
            $reservation->setOfferId($offerId);
            $reservation->setNumberOfPeople($numberOfPeople);
            $reservation->setTotalPrice((string)$totalPrice);
            $reservation->setStatus('PENDING');
            $reservation->setPaymentStatus('PENDING');
            $reservation->setReservationDate(new \DateTime());
            $reservation->setUpdatedAt(new \DateTime());

            $entityManager = $this->entityManager;
            $entityManager->persist($reservation);
            $entityManager->flush();

            return $this->getReservationById($reservation->getId(), $userId);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create reservation', ['error' => $e->getMessage(), 'user_id' => $userId]);
            return null;
        }
    }

    public function getReservationsForUser(int $userId): array
    {
        try {
            $reservations = $this->reservationRepository->findByUserId($userId);
            if (empty($reservations)) {
                return [];
            }

            $voyageIds = array_unique(array_map(fn ($r) => $r->getVoyageId(), $reservations));
            $voyageMap = [];
            foreach ($this->voyageRepository->findByIds($voyageIds) as $v) {
                $voyageMap[$v->getId()] = $v;
            }

            return array_map(function ($reservation) use ($voyageMap) {
                $result = $this->reservationToArray($reservation);
                $voyage = $voyageMap[$reservation->getVoyageId()] ?? null;
                if ($voyage) {
                    $result['voyage_title']       = $voyage->getTitle();
                    $result['voyage_description'] = $voyage->getDescription();
                    $result['destination']        = $voyage->getDestination();
                    $result['voyage_start']       = $voyage->getStartDate()?->format('Y-m-d');
                    $result['voyage_end']         = $voyage->getEndDate()?->format('Y-m-d');
                    $result['voyage_price']       = $voyage->getPrice();
                }
                return $result;
            }, $reservations);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to get reservations for user', ['error' => $e->getMessage(), 'user_id' => $userId]);
            return [];
        }
    }
    public function getReservationById(int $reservationId, int $userId): ?array
    {
        try {
            $reservation = $this->reservationRepository->find($reservationId);
            if (!$reservation || $reservation->getUserId() !== $userId) {
                return null;
            }

            return $this->reservationToArrayWithVoyage($reservation);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to get reservation by id', ['error' => $e->getMessage(), 'reservation_id' => $reservationId, 'user_id' => $userId]);
            return null;
        }
    }

    public function getReservationByUserAndVoyage(int $userId, int $voyageId): ?array
    {
        try {
            $reservation = $this->reservationRepository->findUserVoyageReservation($userId, $voyageId);
            if (!$reservation) {
                return null;
            }

            return $this->reservationToArrayWithVoyage($reservation);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to get reservation by user and voyage', ['error' => $e->getMessage(), 'user_id' => $userId, 'voyage_id' => $voyageId]);
            return null;
        }
    }

    public function cancelReservation(int $reservationId, int $userId): bool
    {
        try {
            $reservation = $this->reservationRepository->find($reservationId);
            if (!$reservation || $reservation->getUserId() !== $userId) {
                return false;
            }

            $currentStatus = $reservation->getStatus();
            if (!in_array($currentStatus, ['PENDING', 'CONFIRMED'], true)) {
                return false;
            }

            $reservation->setStatus('CANCELLED');
            $reservation->setUpdatedAt(new \DateTime());

            $entityManager = $this->entityManager;
            $entityManager->flush();

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to cancel reservation', ['error' => $e->getMessage(), 'reservation_id' => $reservationId, 'user_id' => $userId]);
            return false;
        }
    }

    public function confirmReservationAsAdmin(int $reservationId, ?string $paymentReference = null): bool
    {
        try {
            $reservation = $this->reservationRepository->find($reservationId);
            if (!$reservation || $reservation->getStatus() !== 'PENDING') {
                return false;
            }

            $reservation->setStatus('CONFIRMED');
            $reservation->setPaymentStatus('PAID');
            $reservation->setPaymentDate(new \DateTime());
            if ($paymentReference !== null && trim($paymentReference) !== '') {
                $reservation->setPaymentReference(trim($paymentReference));
            }
            $reservation->setUpdatedAt(new \DateTime());

            $entityManager = $this->entityManager;
            $entityManager->flush();

            // Award loyalty points: 1 point per TND spent
            $this->loyaltyPointsService->awardPoints($reservation->getUserId(), (float) $reservation->getTotalPrice());

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to confirm reservation as admin', ['error' => $e->getMessage(), 'reservation_id' => $reservationId]);
            return false;
        }
    }

    public function cancelReservationAsAdmin(int $reservationId): bool
    {
        try {
            $reservation = $this->reservationRepository->find($reservationId);
            if (!$reservation) {
                return false;
            }

            $currentStatus = $reservation->getStatus();
            if (!in_array($currentStatus, ['PENDING', 'CONFIRMED'], true)) {
                return false;
            }

            $reservation->setStatus('CANCELLED');
            $reservation->setUpdatedAt(new \DateTime());

            $entityManager = $this->entityManager;
            $entityManager->flush();

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to cancel reservation as admin', ['error' => $e->getMessage(), 'reservation_id' => $reservationId]);
            return false;
        }
    }

    public function listAllReservations(): array
    {
        try {
            $reservations = $this->reservationRepository->findAll();
            if (empty($reservations)) {
                return [];
            }

            // Batch-load users and voyages — 2 queries instead of 2N
            $userIds   = array_unique(array_map(fn($r) => $r->getUserId(),   $reservations));
            $voyageIds = array_unique(array_map(fn($r) => $r->getVoyageId(), $reservations));

            $userMap = [];
            foreach ($this->userRepository->findByIds($userIds) as $u) {
                $userMap[$u->getId()] = $u;
            }
            $voyageMap = [];
            foreach ($this->voyageRepository->findByIds($voyageIds) as $v) {
                $voyageMap[$v->getId()] = $v;
            }

            $result = [];
            foreach ($reservations as $reservation) {
                $user   = $userMap[$reservation->getUserId()]   ?? null;
                $voyage = $voyageMap[$reservation->getVoyageId()] ?? null;

                $result[] = [
                    'id'               => $reservation->getId(),
                    'user_id'          => $reservation->getUserId(),
                    'user_name'        => $user?->getUsername(),
                    'voyage_id'        => $reservation->getVoyageId(),
                    'voyage_title'     => $voyage?->getTitle(),
                    'offer_id'         => $reservation->getOfferId(),
                    'reservation_date' => $reservation->getReservationDate(),
                    'number_of_people' => $reservation->getNumberOfPeople(),
                    'total_price'      => $reservation->getTotalPrice(),
                    'status'           => $reservation->getStatus(),
                    'special_requests' => $reservation->getSpecialRequests(),
                    'payment_status'   => $reservation->getPaymentStatus(),
                    'payment_date'     => $reservation->getPaymentDate(),
                    'payment_reference'=> $reservation->getPaymentReference(),
                    'updated_at'       => $reservation->getUpdatedAt(),
                    'user_email'       => $user?->getEmail(),
                    'destination'      => $voyage?->getDestination(),
                ];
            }

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to list all reservations', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function confirmReservation(int $reservationId, int $userId, ?string $paymentReference = null): bool
    {
        try {
            $reservation = $this->reservationRepository->find($reservationId);
            if (!$reservation || $reservation->getUserId() !== $userId || $reservation->getStatus() !== 'PENDING') {
                return false;
            }

            $reservation->setStatus('CONFIRMED');
            $reservation->setPaymentStatus('PAID');
            $reservation->setPaymentDate(new \DateTime());
            if ($paymentReference !== null && trim($paymentReference) !== '') {
                $reservation->setPaymentReference(trim($paymentReference));
            }
            $reservation->setUpdatedAt(new \DateTime());

            $entityManager = $this->entityManager;
            $entityManager->flush();

            // Award loyalty points: 1 point per TND spent
            $this->loyaltyPointsService->awardPoints($userId, (float) $reservation->getTotalPrice());

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to confirm reservation', ['error' => $e->getMessage(), 'reservation_id' => $reservationId, 'user_id' => $userId]);
            return false;
        }
    }

    public function getReservationByIdAdmin(int $reservationId): ?array
    {
        try {
            $reservation = $this->reservationRepository->find($reservationId);
            if (!$reservation) {
                return null;
            }

            return $this->reservationToArrayWithUserAndVoyage($reservation);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to get reservation by id admin', ['error' => $e->getMessage(), 'reservation_id' => $reservationId]);
            return null;
        }
    }

    public function requestRefund(int $reservationId, int $userId, string $reason): bool
    {
        $eligibility = $this->evaluateRefundEligibility($reservationId, $userId);
        if (!$eligibility['eligible']) {
            $this->logger->warning('Refund request rejected by eligibility rules', [
                'reservation_id' => $reservationId,
                'user_id' => $userId,
                'reason' => $eligibility['reason'] ?? 'Unknown',
            ]);
            return false;
        }

        $reservation = $this->reservationRepository->find($reservationId);
        if (!$reservation) {
            return false;
        }

        try {
             $refundRequest = $this->refundRequestService->createRefundRequest([
        'reclamation_id' => null,
        'requester_id' => $userId,
           'reservation_id' => $reservationId,
        'amount' => $reservation->getTotalPrice(),
        'reason' => $reason,
    ]);
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to request refund', ['error' => $e->getMessage(), 'reservation_id' => $reservationId, 'user_id' => $userId]);
            return false;
        }
    }

    /**
     * Convert Reservation entity to array with basic fields
     */
    private function reservationToArray(Reservation $reservation): array
    {
        return [
            'id' => $reservation->getId(),
            'user_id' => $reservation->getUserId(),
            'voyage_id' => $reservation->getVoyageId(),
            'offer_id' => $reservation->getOfferId(),
            'number_of_people' => $reservation->getNumberOfPeople(),
            'total_price' => $reservation->getTotalPrice(),
            'status' => $reservation->getStatus(),
            'payment_status' => $reservation->getPaymentStatus(),
            'payment_reference' => $reservation->getPaymentReference(),
            'reservation_date' => $reservation->getReservationDate()?->format('Y-m-d H:i:s'),
            'updated_at' => $reservation->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Convert Reservation entity to array with voyage details
     */
    private function reservationToArrayWithVoyage(Reservation $reservation): array
    {
        $result = $this->reservationToArray($reservation);

        try {
            $voyage = $this->voyageRepository->find($reservation->getVoyageId());
            if ($voyage) {
                $result['voyage_title'] = $voyage->getTitle();
                $result['voyage_description'] = $voyage->getDescription();
                $result['destination'] = $voyage->getDestination();
                $result['voyage_start'] = $voyage->getStartDate()?->format('Y-m-d');
                $result['voyage_end'] = $voyage->getEndDate()?->format('Y-m-d');
                $result['voyage_price'] = $voyage->getPrice();
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to load voyage details', ['error' => $e->getMessage()]);
        }

        return $result;
    }

    /**
     * Convert Reservation entity to array with user and voyage details
     */
    private function reservationToArrayWithUserAndVoyage(Reservation $reservation): array
    {
        $result = $this->reservationToArrayWithVoyage($reservation);

        try {
            $user = $this->userRepository->find($reservation->getUserId());
            if ($user) {
                $result['user_name'] = $user->getUsername();
                $result['user_email'] = $user->getEmail();
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to load user details', ['error' => $e->getMessage()]);
        }

        return $result;
    }
}