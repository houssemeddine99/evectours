<?php

namespace App\Utility;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

class DatabaseInitializer
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    private function isMySQL(): bool
    {
        return str_contains(strtolower($this->connection->getDriver()::class), 'mysql')
            || str_contains(strtolower((string) $this->connection->getDatabasePlatform()::class), 'mysql')
            || str_contains(strtolower((string) $this->connection->getDatabasePlatform()::class), 'mariadb');
    }

    /** Returns the auto-increment primary key definition for the current driver */
    private function pk(): string
    {
        return $this->isMySQL()
            ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY'
            : 'SERIAL PRIMARY KEY';
    }

    public function ensureSchema(): void
    {
        try {
            $pk = $this->pk();

            $this->connection->executeStatement("
CREATE TABLE IF NOT EXISTS voyages (
    id $pk,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    destination VARCHAR(255) NOT NULL,
    start_date DATE,
    end_date DATE,
    price DECIMAL(10,2),
    image_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

            $this->connection->executeStatement("
CREATE TABLE IF NOT EXISTS activities (
    id $pk,
    voyage_id INTEGER NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    duration_hours INTEGER,
    price_per_person DECIMAL(10,2) DEFAULT 0.00,
    location VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activities_voyage FOREIGN KEY (voyage_id) REFERENCES voyages(id) ON DELETE CASCADE
)");

            $this->connection->executeStatement("
CREATE TABLE IF NOT EXISTS users (
    id $pk,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    tel VARCHAR(20),
    image_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

            $this->connection->executeStatement("
CREATE TABLE IF NOT EXISTS admins (
    user_id INTEGER PRIMARY KEY,
    access_level INTEGER DEFAULT 1,
    CONSTRAINT fk_admins_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

            $this->connection->executeStatement("
CREATE TABLE IF NOT EXISTS offers (
    id $pk,
    voyage_id INTEGER NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    discount_percentage DECIMAL(5,2),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_offers_voyage FOREIGN KEY (voyage_id) REFERENCES voyages(id) ON DELETE CASCADE
)");

            $this->connection->executeStatement("
CREATE TABLE IF NOT EXISTS reservations (
    id $pk,
    user_id INTEGER NOT NULL,
    voyage_id INTEGER NOT NULL,
    offer_id INTEGER NULL,
    reservation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    number_of_people INTEGER NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'PENDING',
    special_requests TEXT,
    payment_status VARCHAR(20) DEFAULT 'PENDING',
    payment_date TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_user_voyage UNIQUE(user_id, voyage_id),
    CONSTRAINT fk_reservations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reservations_voyage FOREIGN KEY (voyage_id) REFERENCES voyages(id) ON DELETE CASCADE,
    CONSTRAINT fk_reservations_offer FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE SET NULL
)");

            $this->connection->executeStatement("
CREATE TABLE IF NOT EXISTS reclamations (
    id $pk,
    reservation_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    reclamation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'OPEN',
    priority VARCHAR(10) DEFAULT 'MEDIUM',
    admin_response TEXT,
    response_date TIMESTAMP NULL,
    resolution_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reclamations_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    CONSTRAINT fk_reclamations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

            $this->connection->executeStatement("
CREATE TABLE IF NOT EXISTS user_documents (
    id $pk,
    user_id INTEGER NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    date_of_birth DATE,
    nationality VARCHAR(100),
    passport_number VARCHAR(255),
    passport_expiry_date DATE,
    cin_number VARCHAR(255),
    cin_creation_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_documents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

            $this->connection->executeStatement("
CREATE TABLE IF NOT EXISTS refund_requests (
    id $pk,
    reclamation_id INTEGER,
    requester_id INTEGER NOT NULL,
    reservation_id INTEGER,
    amount DECIMAL(10,2) NOT NULL,
    reason TEXT,
    status VARCHAR(20) DEFAULT 'PENDING',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_refund_requests_reclamation FOREIGN KEY (reclamation_id) REFERENCES reclamations(id) ON DELETE CASCADE,
    CONSTRAINT fk_refund_requests_requester FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE
)");

            $this->connection->executeStatement("
CREATE TABLE IF NOT EXISTS user_offers (
    id $pk,
    user_id INTEGER NOT NULL,
    offer_id INTEGER NOT NULL,
    claimed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'ACTIVE',
    CONSTRAINT unique_user_offer UNIQUE(user_id, offer_id),
    CONSTRAINT fk_user_offers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_offers_offer FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE
)");

            $this->connection->executeStatement("
CREATE TABLE IF NOT EXISTS associations (
    id $pk,
    name VARCHAR(255) NOT NULL,
    company_code VARCHAR(50) UNIQUE NOT NULL,
    discount_rate DECIMAL(5,2) DEFAULT 0.00
)");

            $this->connection->executeStatement("
CREATE TABLE IF NOT EXISTS user_associations (
    user_id INTEGER PRIMARY KEY,
    association_id INTEGER,
    CONSTRAINT fk_user_associations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_associations_association FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE
)");

            $this->connection->executeStatement("
CREATE TABLE IF NOT EXISTS voyage_images (
    id $pk,
    voyage_id INTEGER NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    cloudinary_public_id VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_voyage_images_voyage FOREIGN KEY (voyage_id) REFERENCES voyages(id) ON DELETE CASCADE
)");

            $this->connection->executeStatement("
CREATE TABLE IF NOT EXISTS user_logins (
    id $pk,
    user_id INT NOT NULL,
    login_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    login_method VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    CONSTRAINT fk_user_logins_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

            $this->connection->executeStatement("
CREATE TABLE IF NOT EXISTS search_history (
    id $pk,
    user_id INT NOT NULL,
    search_query VARCHAR(255) NOT NULL,
    search_type VARCHAR(50) NOT NULL,
    search_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    results_found INT DEFAULT 0,
    CONSTRAINT fk_search_history_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

            $this->connection->executeStatement("
CREATE TABLE IF NOT EXISTS voyage_visits (
    id $pk,
    user_id INT NOT NULL,
    voyage_id INT NOT NULL,
    visit_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    source VARCHAR(50) DEFAULT 'direct',
    view_duration_seconds INT DEFAULT 0,
    CONSTRAINT fk_voyage_visits_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_voyage_visits_voyage FOREIGN KEY (voyage_id) REFERENCES voyages(id) ON DELETE CASCADE
)");

            $this->connection->executeStatement("
CREATE TABLE IF NOT EXISTS offer_views (
    id $pk,
    user_id INT NOT NULL,
    offer_id INT NOT NULL,
    view_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    clicked BOOLEAN DEFAULT FALSE,
    CONSTRAINT fk_offer_views_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_offer_views_offer FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE
)");

            /* ── Tags ── */
            $this->connection->executeStatement("
CREATE TABLE IF NOT EXISTS tags (
    id $pk,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(30) NULL
)");

            $this->connection->executeStatement("
CREATE TABLE IF NOT EXISTS voyage_tags (
    voyage_id INTEGER NOT NULL,
    tag_id INTEGER NOT NULL,
    PRIMARY KEY (voyage_id, tag_id),
    CONSTRAINT fk_vt_voyage FOREIGN KEY (voyage_id) REFERENCES voyages(id) ON DELETE CASCADE,
    CONSTRAINT fk_vt_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
)");

            /* ── Favorites ── */
            $this->connection->executeStatement("
CREATE TABLE IF NOT EXISTS favorites (
    id $pk,
    user_id INTEGER NOT NULL,
    voyage_id INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_favorite UNIQUE(user_id, voyage_id),
    CONSTRAINT fk_favorites_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_favorites_voyage FOREIGN KEY (voyage_id) REFERENCES voyages(id) ON DELETE CASCADE
)");

            /* ── Reviews ── */
            $this->connection->executeStatement("
CREATE TABLE IF NOT EXISTS reviews (
    id $pk,
    voyage_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    rating INTEGER NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_review UNIQUE(voyage_id, user_id),
    CONSTRAINT fk_reviews_voyage FOREIGN KEY (voyage_id) REFERENCES voyages(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

            /* ── Loyalty points ── */
            $this->connection->executeStatement("
CREATE TABLE IF NOT EXISTS loyalty_points (
    id $pk,
    user_id INTEGER NOT NULL UNIQUE,
    points INTEGER DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_loyalty_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

            /* ── Waitlist ── */
            $this->connection->executeStatement("
CREATE TABLE IF NOT EXISTS waitlist_entries (
    id $pk,
    voyage_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notified BOOLEAN DEFAULT FALSE,
    CONSTRAINT unique_waitlist UNIQUE(voyage_id, user_id),
    CONSTRAINT fk_waitlist_voyage FOREIGN KEY (voyage_id) REFERENCES voyages(id) ON DELETE CASCADE,
    CONSTRAINT fk_waitlist_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

            $this->logger->info('Database schema verified/initialized successfully.');
        } catch (\Throwable $e) {
            $this->logger->error('Schema initialization failed: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
