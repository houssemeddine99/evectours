<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add loyalty_points, waitlist_entries tables and flash sale columns on offers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS loyalty_points (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            points_balance INT NOT NULL DEFAULT 0,
            updated_at TIMESTAMP NOT NULL DEFAULT NOW()
        )');

        $this->addSql('CREATE TABLE IF NOT EXISTS waitlist_entries (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL,
            voyage_id INT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT NOW(),
            notified BOOLEAN NOT NULL DEFAULT FALSE,
            CONSTRAINT uniq_user_voyage_waitlist UNIQUE (user_id, voyage_id)
        )');

        $this->addSql('ALTER TABLE offers ADD COLUMN IF NOT EXISTS flash_sale_ends_at TIMESTAMP NULL');
        $this->addSql('ALTER TABLE offers ADD COLUMN IF NOT EXISTS flash_sale_discount DECIMAL(5,2) NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS waitlist_entries');
        $this->addSql('DROP TABLE IF EXISTS loyalty_points');
        $this->addSql('ALTER TABLE offers DROP COLUMN IF EXISTS flash_sale_ends_at');
        $this->addSql('ALTER TABLE offers DROP COLUMN IF EXISTS flash_sale_discount');
    }
}
