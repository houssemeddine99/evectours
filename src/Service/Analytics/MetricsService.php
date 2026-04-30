<?php

namespace App\Service\Analytics;

use Doctrine\DBAL\Connection;

/**
 * Service to collect and aggregate application-wide metrics.
 * Provides a 360° view for dashboards, reports, and AI context.
 * Compatible with both MySQL/MariaDB and PostgreSQL.
 */
class MetricsService
{
    public function __construct(private Connection $connection) {}

    private function isMySQL(): bool
    {
        $platform = strtolower($this->connection->getDatabasePlatform()::class);
        return str_contains($platform, 'mysql') || str_contains($platform, 'mariadb');
    }

    // -------------------------------------------------------------------------
    // 1. Event & usage metrics (based on unified_events)
    // -------------------------------------------------------------------------

    public function getEventTypeDistribution(string $startDate, string $endDate): array
    {
        $sql = '
            SELECT type, COUNT(*) as count
            FROM unified_events
            WHERE created_at BETWEEN :start AND :end
            GROUP BY type
        ';
        $rows = $this->connection->fetchAllAssociative($sql, [
            'start' => $startDate,
            'end'   => $endDate,
        ]);
        return array_column($rows, 'count', 'type');
    }

    public function getSearchToViewConversionRate(string $startDate, string $endDate): array
    {
        if ($this->isMySQL()) {
            $sql = '
                SELECT
                    COUNT(DISTINCT CASE WHEN type = \'search\' THEN user_id END) AS searchers,
                    COUNT(DISTINCT CASE WHEN type = \'voyage_visits\' THEN user_id END) AS viewers
                FROM unified_events
                WHERE created_at BETWEEN :start AND :end
            ';
        } else {
            $sql = '
                SELECT
                    COUNT(DISTINCT user_id) FILTER (WHERE type = \'search\') AS searchers,
                    COUNT(DISTINCT user_id) FILTER (WHERE type = \'voyage_visits\') AS viewers
                FROM unified_events
                WHERE created_at BETWEEN :start AND :end
            ';
        }
        $result    = $this->connection->fetchAssociative($sql, ['start' => $startDate, 'end' => $endDate]);
        $searchers = (int) $result['searchers'];
        $viewers   = (int) $result['viewers'];
        return [
            'searchers'       => $searchers,
            'viewers'         => $viewers,
            'conversion_rate' => $searchers > 0 ? round(100 * $viewers / $searchers, 1) : 0,
        ];
    }

    public function getAverageVoyageViewDuration(): float
    {
        if ($this->isMySQL()) {
            $sql = "SELECT AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(data, '$.duration')) AS UNSIGNED)) as avg_duration
                    FROM unified_events WHERE type = 'voyage_visits'";
        } else {
            $sql = "SELECT AVG((data->>'duration')::int) as avg_duration FROM unified_events WHERE type = 'voyage_visits'";
        }
        $result = $this->connection->fetchAssociative($sql);
        return (float) ($result['avg_duration'] ?? 0);
    }

    public function getTopSearchedDestinations(int $limit = 5): array
    {
        if ($this->isMySQL()) {
            $sql = "
                SELECT
                    TRIM(SUBSTRING_INDEX(JSON_UNQUOTE(JSON_EXTRACT(data, '$.query')), ' ', 1)) AS destination,
                    COUNT(*) as search_count
                FROM unified_events
                WHERE type = 'search'
                  AND JSON_UNQUOTE(JSON_EXTRACT(data, '$.query')) IS NOT NULL
                GROUP BY destination
                ORDER BY search_count DESC
                LIMIT :limit
            ";
        } else {
            $sql = "
                SELECT
                    TRIM(SPLIT_PART(data->>'query', ' ', 1)) AS destination,
                    COUNT(*) as search_count
                FROM unified_events
                WHERE type = 'search' AND data->>'query' IS NOT NULL
                GROUP BY destination
                ORDER BY search_count DESC
                LIMIT :limit
            ";
        }
        return $this->connection->fetchAllAssociative($sql, ['limit' => $limit]);
    }

    public function getUnmetDemandDestinations(int $limit = 5): array
    {
        if ($this->isMySQL()) {
            $sql = "
                SELECT
                    TRIM(SUBSTRING_INDEX(JSON_UNQUOTE(JSON_EXTRACT(data, '$.query')), ' ', 1)) AS destination,
                    COUNT(*) as search_count
                FROM unified_events
                WHERE type = 'search'
                  AND JSON_UNQUOTE(JSON_EXTRACT(data, '$.query')) IS NOT NULL
                  AND TRIM(SUBSTRING_INDEX(JSON_UNQUOTE(JSON_EXTRACT(data, '$.query')), ' ', 1))
                      NOT IN (SELECT DISTINCT destination FROM voyages)
                GROUP BY destination
                ORDER BY search_count DESC
                LIMIT :limit
            ";
        } else {
            $sql = "
                SELECT
                    TRIM(SPLIT_PART(data->>'query', ' ', 1)) AS destination,
                    COUNT(*) as search_count
                FROM unified_events
                WHERE type = 'search'
                  AND data->>'query' IS NOT NULL
                  AND TRIM(SPLIT_PART(data->>'query', ' ', 1)) NOT IN (
                      SELECT DISTINCT destination FROM voyages
                  )
                GROUP BY destination
                ORDER BY search_count DESC
                LIMIT :limit
            ";
        }
        return $this->connection->fetchAllAssociative($sql, ['limit' => $limit]);
    }

    // -------------------------------------------------------------------------
    // 2. Voyage & destination metrics
    // -------------------------------------------------------------------------

    public function getTopVoyagesByVisits(int $limit = 10): array
    {
        $sql = '
            SELECT v.id, v.title, v.destination, v.price,
                   COUNT(vv.id) as visit_count
            FROM voyages v
            LEFT JOIN voyage_visits vv ON v.id = vv.voyage_id
            GROUP BY v.id, v.title, v.destination, v.price
            ORDER BY visit_count DESC
            LIMIT :limit
        ';
        return $this->connection->fetchAllAssociative($sql, ['limit' => $limit]);
    }

    public function getBestVoyage(): ?array
    {
        $top = $this->getTopVoyagesByVisits(1);
        return $top[0] ?? null;
    }

    public function getVoyageDetails(string $identifier): ?array
    {
        if ($this->isMySQL()) {
            $sql = '
                SELECT id, title, description, destination,
                       start_date, end_date, price, image_url
                FROM voyages
                WHERE CAST(id AS CHAR) = :identifier
                   OR title LIKE :title_pattern
                LIMIT 1
            ';
        } else {
            $sql = '
                SELECT id, title, description, destination,
                       start_date, end_date, price, image_url
                FROM voyages
                WHERE CAST(id AS TEXT) = :identifier
                   OR title ILIKE :title_pattern
                LIMIT 1
            ';
        }
        $result = $this->connection->fetchAssociative($sql, [
            'identifier'    => $identifier,
            'title_pattern' => '%' . $identifier . '%',
        ]);
        return $result ?: null;
    }

    public function getTopDestinations(int $limit = 5): array
    {
        $sql = '
            SELECT v.destination, COUNT(vv.id) as visit_count
            FROM voyages v
            LEFT JOIN voyage_visits vv ON v.id = vv.voyage_id
            GROUP BY v.destination
            ORDER BY visit_count DESC
            LIMIT :limit
        ';
        return $this->connection->fetchAllAssociative($sql, ['limit' => $limit]);
    }

    // -------------------------------------------------------------------------
    // 3. User & login metrics
    // -------------------------------------------------------------------------

    public function getUserGrowthStats(string $startDate, string $endDate): array
    {
        $totalUsers = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM users');
        $newUsers   = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM users WHERE created_at BETWEEN :start AND :end',
            ['start' => $startDate, 'end' => $endDate]
        );
        return [
            'period'      => ['start' => $startDate, 'end' => $endDate],
            'total_users' => $totalUsers,
            'new_users'   => $newUsers,
            'growth_rate' => $totalUsers > 0 ? round(100 * $newUsers / $totalUsers, 1) : 0,
        ];
    }

    public function getTotalLogins(string $startDate, string $endDate): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM user_logins WHERE login_time BETWEEN :start AND :end',
            ['start' => $startDate, 'end' => $endDate]
        );
    }

    public function getAverageLoginsPerUser(string $startDate, string $endDate): float
    {
        $sql = '
            SELECT AVG(login_count)
            FROM (
                SELECT user_id, COUNT(*) as login_count
                FROM user_logins
                WHERE login_time BETWEEN :start AND :end
                GROUP BY user_id
            ) sub
        ';
        return (float) ($this->connection->fetchOne($sql, ['start' => $startDate, 'end' => $endDate]) ?: 0);
    }

    public function getActiveUsers(string $startDate, string $endDate): int
    {
        $sql = '
            SELECT COUNT(DISTINCT user_id) FROM (
                SELECT user_id FROM user_logins WHERE login_time BETWEEN :start AND :end
                UNION
                SELECT user_id FROM unified_events WHERE created_at BETWEEN :start AND :end
            ) active
        ';
        return (int) $this->connection->fetchOne($sql, ['start' => $startDate, 'end' => $endDate]);
    }

    public function getAllUsers(int $limit = 50): array
    {
        $sql = 'SELECT id, username, email, tel, created_at FROM users ORDER BY id LIMIT :limit';
        return $this->connection->fetchAllAssociative($sql, ['limit' => $limit]);
    }

    // -------------------------------------------------------------------------
    // 4. Reservation & revenue metrics
    // -------------------------------------------------------------------------

    public function getReservationSummary(string $startDate, string $endDate): array
    {
        if ($this->isMySQL()) {
            $sql = "
                SELECT
                    COUNT(*) as total_reservations,
                    SUM(CASE WHEN status = 'CONFIRMED' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN status = 'PENDING'   THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled,
                    COALESCE(SUM(CASE WHEN status = 'CONFIRMED' THEN total_price ELSE 0 END), 0) as total_revenue
                FROM reservations
                WHERE reservation_date BETWEEN :start AND :end
            ";
        } else {
            $sql = "
                SELECT
                    COUNT(*) as total_reservations,
                    COUNT(*) FILTER (WHERE status = 'CONFIRMED') as confirmed,
                    COUNT(*) FILTER (WHERE status = 'PENDING')   as pending,
                    COUNT(*) FILTER (WHERE status = 'CANCELLED') as cancelled,
                    COALESCE(SUM(total_price) FILTER (WHERE status = 'CONFIRMED'), 0) as total_revenue
                FROM reservations
                WHERE reservation_date BETWEEN :start AND :end
            ";
        }
        $result = $this->connection->fetchAssociative($sql, ['start' => $startDate, 'end' => $endDate]);
        return [
            'total_reservations' => (int)   $result['total_reservations'],
            'confirmed'          => (int)   $result['confirmed'],
            'pending'            => (int)   $result['pending'],
            'cancelled'          => (int)   $result['cancelled'],
            'total_revenue'      => (float) $result['total_revenue'],
        ];
    }

    public function getAverageReservationValue(string $startDate, string $endDate): float
    {
        $sql = "
            SELECT AVG(total_price) as avg_value
            FROM reservations
            WHERE status = 'CONFIRMED' AND reservation_date BETWEEN :start AND :end
        ";
        return (float) ($this->connection->fetchOne($sql, ['start' => $startDate, 'end' => $endDate]) ?: 0);
    }

    // -------------------------------------------------------------------------
    // 5. Payment metrics
    // -------------------------------------------------------------------------

    public function getPaymentStatusDistribution(string $startDate, string $endDate): array
    {
        $sql = '
            SELECT payment_status, COUNT(*) as count
            FROM reservations
            WHERE reservation_date BETWEEN :start AND :end
            GROUP BY payment_status
        ';
        $rows = $this->connection->fetchAllAssociative($sql, ['start' => $startDate, 'end' => $endDate]);
        $dist = [];
        foreach ($rows as $row) {
            $dist[$row['payment_status']] = (int) $row['count'];
        }
        return $dist;
    }

    public function getPaymentSuccessRate(string $startDate, string $endDate): float
    {
        if ($this->isMySQL()) {
            $sql = "
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN payment_status = 'PAID' THEN 1 ELSE 0 END) as paid
                FROM reservations
                WHERE reservation_date BETWEEN :start AND :end
            ";
        } else {
            $sql = "
                SELECT
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE payment_status = 'PAID') as paid
                FROM reservations
                WHERE reservation_date BETWEEN :start AND :end
            ";
        }
        $result = $this->connection->fetchAssociative($sql, ['start' => $startDate, 'end' => $endDate]);
        $total  = (int) $result['total'];
        $paid   = (int) $result['paid'];
        return $total > 0 ? round(100 * $paid / $total, 1) : 0;
    }

    // -------------------------------------------------------------------------
    // 6. Reclamation & refund metrics
    // -------------------------------------------------------------------------

    public function getReclamationSummary(string $startDate, string $endDate): array
    {
        if ($this->isMySQL()) {
            $sql = "
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'OPEN'        THEN 1 ELSE 0 END) as open,
                    SUM(CASE WHEN status = 'IN_PROGRESS' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'RESOLVED'    THEN 1 ELSE 0 END) as resolved,
                    SUM(CASE WHEN priority = 'HIGH'      THEN 1 ELSE 0 END) as high_priority
                FROM reclamations
                WHERE created_at BETWEEN :start AND :end
            ";
        } else {
            $sql = "
                SELECT
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE status = 'OPEN')        as open,
                    COUNT(*) FILTER (WHERE status = 'IN_PROGRESS') as in_progress,
                    COUNT(*) FILTER (WHERE status = 'RESOLVED')    as resolved,
                    COUNT(*) FILTER (WHERE priority = 'HIGH')      as high_priority
                FROM reclamations
                WHERE created_at BETWEEN :start AND :end
            ";
        }
        $result = $this->connection->fetchAssociative($sql, ['start' => $startDate, 'end' => $endDate]);
        return [
            'total'         => (int) $result['total'],
            'open'          => (int) $result['open'],
            'in_progress'   => (int) $result['in_progress'],
            'resolved'      => (int) $result['resolved'],
            'high_priority' => (int) $result['high_priority'],
        ];
    }

    public function getAverageResolutionTimeHours(): float
    {
        if ($this->isMySQL()) {
            $sql = "
                SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, resolution_date) / 3600) as avg_hours
                FROM reclamations
                WHERE status = 'RESOLVED' AND resolution_date IS NOT NULL
            ";
        } else {
            $sql = "
                SELECT AVG(EXTRACT(EPOCH FROM (resolution_date - created_at)) / 3600) as avg_hours
                FROM reclamations
                WHERE status = 'RESOLVED' AND resolution_date IS NOT NULL
            ";
        }
        return (float) ($this->connection->fetchOne($sql) ?: 0);
    }

    public function getRefundRequestSummary(string $startDate, string $endDate): array
    {
        if ($this->isMySQL()) {
            $sql = "
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'PENDING'  THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'REJECTED' THEN 1 ELSE 0 END) as rejected,
                    COALESCE(SUM(CASE WHEN status = 'APPROVED' THEN amount ELSE 0 END), 0) as total_approved_amount
                FROM refund_requests
                WHERE created_at BETWEEN :start AND :end
            ";
        } else {
            $sql = "
                SELECT
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE status = 'PENDING')  as pending,
                    COUNT(*) FILTER (WHERE status = 'APPROVED') as approved,
                    COUNT(*) FILTER (WHERE status = 'REJECTED') as rejected,
                    COALESCE(SUM(amount) FILTER (WHERE status = 'APPROVED'), 0) as total_approved_amount
                FROM refund_requests
                WHERE created_at BETWEEN :start AND :end
            ";
        }
        $result = $this->connection->fetchAssociative($sql, ['start' => $startDate, 'end' => $endDate]);
        return [
            'total'                 => (int)   $result['total'],
            'pending'               => (int)   $result['pending'],
            'approved'              => (int)   $result['approved'],
            'rejected'              => (int)   $result['rejected'],
            'total_approved_amount' => (float) $result['total_approved_amount'],
        ];
    }

    // -------------------------------------------------------------------------
    // 7. Offer & activity metrics
    // -------------------------------------------------------------------------

    public function getActiveOffersStats(): array
    {
        $sql = "
            SELECT
                COUNT(*) as total_active,
                AVG(discount_percentage) as avg_discount
            FROM offers
            WHERE is_active = 1
              AND start_date <= CURRENT_DATE
              AND end_date >= CURRENT_DATE
        ";
        $result = $this->connection->fetchAssociative($sql);
        return [
            'total_active' => (int)   ($result['total_active'] ?? 0),
            'avg_discount' => (float) ($result['avg_discount'] ?? 0),
        ];
    }

    public function getTopClaimedOffers(int $limit = 5): array
    {
        $sql = '
            SELECT o.id, o.title, o.discount_percentage, COUNT(uo.id) as claim_count
            FROM offers o
            JOIN user_offers uo ON o.id = uo.offer_id
            GROUP BY o.id, o.title, o.discount_percentage
            ORDER BY claim_count DESC
            LIMIT :limit
        ';
        return $this->connection->fetchAllAssociative($sql, ['limit' => $limit]);
    }

    public function getMostPopularActivities(int $limit = 5): array
    {
        $sql = '
            SELECT a.name, COUNT(r.id) as reservation_count
            FROM activities a
            JOIN voyages v ON a.voyage_id = v.id
            JOIN reservations r ON v.id = r.voyage_id
            GROUP BY a.id, a.name
            ORDER BY reservation_count DESC
            LIMIT :limit
        ';
        return $this->connection->fetchAllAssociative($sql, ['limit' => $limit]);
    }

    public function getVoyagesWithMostReclamations(int $limit = 5): array
    {
        $sql = '
            SELECT v.id, v.title, v.destination, COUNT(r.id) as reclamation_count
            FROM voyages v
            JOIN reservations res ON v.id = res.voyage_id
            JOIN reclamations r ON res.id = r.reservation_id
            GROUP BY v.id, v.title, v.destination
            ORDER BY reclamation_count DESC
            LIMIT :limit
        ';
        return $this->connection->fetchAllAssociative($sql, ['limit' => $limit]);
    }

    // -------------------------------------------------------------------------
    // 8. Comprehensive snapshot for AI / dashboard
    // -------------------------------------------------------------------------

    public function getFullAnalyticsSnapshot(?string $startDate = null, ?string $endDate = null): array
    {
        $endDate   = $endDate   ?? date('Y-m-d');
        $startDate = $startDate ?? date('Y-m-d', strtotime('-30 days', strtotime($endDate)));

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],

            'event_type_distribution'      => $this->getEventTypeDistribution($startDate, $endDate),
            'search_to_view_conversion'    => $this->getSearchToViewConversionRate($startDate, $endDate),
            'avg_voyage_view_duration_sec' => $this->getAverageVoyageViewDuration(),
            'top_searched_destinations'    => $this->getTopSearchedDestinations(10),
            'unmet_demand_destinations'    => $this->getUnmetDemandDestinations(5),

            'top_voyages_by_visits'        => $this->getTopVoyagesByVisits(10),
            'best_voyage'                  => $this->getBestVoyage(),

            'user_growth'                  => $this->getUserGrowthStats($startDate, $endDate),
            'total_logins'                 => $this->getTotalLogins($startDate, $endDate),
            'avg_logins_per_user'          => $this->getAverageLoginsPerUser($startDate, $endDate),
            'active_users'                 => $this->getActiveUsers($startDate, $endDate),

            'reservation_summary'          => $this->getReservationSummary($startDate, $endDate),
            'avg_reservation_value'        => $this->getAverageReservationValue($startDate, $endDate),

            'payment_status_distribution'  => $this->getPaymentStatusDistribution($startDate, $endDate),
            'payment_success_rate'         => $this->getPaymentSuccessRate($startDate, $endDate),

            'reclamation_summary'          => $this->getReclamationSummary($startDate, $endDate),
            'avg_resolution_time_hours'    => $this->getAverageResolutionTimeHours(),
            'refund_summary'               => $this->getRefundRequestSummary($startDate, $endDate),

            'active_offers_stats'          => $this->getActiveOffersStats(),
            'top_claimed_offers'           => $this->getTopClaimedOffers(5),
            'most_popular_activities'      => $this->getMostPopularActivities(5),
        ];
    }
}
