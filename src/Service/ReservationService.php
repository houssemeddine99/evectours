<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

class ReservationService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger
    ) {
    }

    public function createReservation(int $userId, int $voyageId, ?int $offerId, int $numberOfPeople, float $totalPrice): ?array
    {
        if ($numberOfPeople <= 0 || $totalPrice < 0) {
            return null;
        }

        try {
            $this->connection->insert('reservations', [
                'user_id' => $userId,
                'voyage_id' => $voyageId,
                'offer_id' => $offerId,
                'number_of_people' => $numberOfPeople,
                'total_price' => $totalPrice,
                'status' => 'PENDING',
                'payment_status' => 'PENDING',
                'reservation_date' => (new \DateTime())->format('Y-m-d H:i:s'),
                'updated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            $id = (int) $this->connection->lastInsertId();
            return $this->getReservationById($id, $userId);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create reservation', ['error' => $e->getMessage(), 'user_id' => $userId]);
            return null;
        }
    }

    public function getReservationsForUser(int $userId): array
    {
        $sql = <<<'SQL'
SELECT r.*, v.title AS voyage_title, v.destination, v.start_date AS voyage_start, v.end_date AS voyage_end
FROM reservations r
JOIN voyages v ON v.id = r.voyage_id
WHERE r.user_id = :user_id
ORDER BY r.reservation_date DESC
SQL;

        return $this->connection->fetchAllAssociative($sql, ['user_id' => $userId]);
    }

    public function getReservationById(int $reservationId, int $userId): ?array
    {
        $sql = <<<'SQL'
SELECT r.*, v.title AS voyage_title, v.description AS voyage_description, v.destination, v.start_date AS voyage_start, v.end_date AS voyage_end, v.price AS voyage_price
FROM reservations r
JOIN voyages v ON v.id = r.voyage_id
WHERE r.id = :id AND r.user_id = :user_id
SQL;

        $reservation = $this->connection->fetchAssociative($sql, ['id' => $reservationId, 'user_id' => $userId]);

        if (!$reservation) {
            return null;
        }

        return $reservation;
    }

    public function cancelReservation(int $reservationId, int $userId): bool
    {
        try {
            $updated = $this->connection->executeStatement(
                'UPDATE reservations SET status = :cancelled, updated_at = :now WHERE id = :id AND user_id = :user_id AND status IN (\'PENDING\', \'CONFIRMED\')',
                [
                    'cancelled' => 'CANCELLED',
                    'now' => (new \DateTime())->format('Y-m-d H:i:s'),
                    'id' => $reservationId,
                    'user_id' => $userId,
                ]
            );

            return $updated > 0;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to cancel reservation', ['error' => $e->getMessage(), 'reservation_id' => $reservationId, 'user_id' => $userId]);
            return false;
        }
    }

    public function requestRefund(int $reservationId, int $userId, string $reason): bool
    {
        // Only allow if reservation belongs to user and is not PENDING or CANCELLED -> maybe only CONFIRMED or COMPLETED
        $reservation = $this->getReservationById($reservationId, $userId);
        if (!$reservation) {
            return false;
        }

        if (!in_array($reservation['status'], ['CONFIRMED', 'CANCELLED', 'COMPLETED'], true)) {
            return false;
        }

        try {
            $this->connection->insert('refund_requests', [
                'reclamation_id' => null,
                'requester_id' => $userId,
                'amount' => $reservation['total_price'],
                'reason' => trim($reason),
                'status' => 'PENDING',
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to request refund', ['error' => $e->getMessage(), 'reservation_id' => $reservationId, 'user_id' => $userId]);
            return false;
        }
    }
}
