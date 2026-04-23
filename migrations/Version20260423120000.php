<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add slug to voyages, tags table, voyage_tags join table, favorites table';
    }

    public function up(Schema $schema): void
    {
        // Add slug column to voyages
        $this->addSql("ALTER TABLE voyages ADD COLUMN IF NOT EXISTS slug VARCHAR(255) NOT NULL DEFAULT ''");
        // Backfill slugs from existing titles (voyage-{id} as safe unique fallback)
        $this->addSql("UPDATE voyages SET slug = 'voyage-' || id::text WHERE slug = ''");
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_voyage_slug ON voyages (slug)');

        // Tags table
        $this->addSql('CREATE TABLE IF NOT EXISTS tags (
            id SERIAL PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            color VARCHAR(30) DEFAULT NULL
        )');

        // Voyage <-> Tag join table
        $this->addSql('CREATE TABLE IF NOT EXISTS voyage_tags (
            voyage_id INT NOT NULL,
            tag_id INT NOT NULL,
            PRIMARY KEY (voyage_id, tag_id)
        )');

        // Favorites table
        $this->addSql('CREATE TABLE IF NOT EXISTS favorites (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL,
            voyage_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT NOW(),
            CONSTRAINT uniq_user_voyage UNIQUE (user_id, voyage_id)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS favorites');
        $this->addSql('DROP TABLE IF EXISTS voyage_tags');
        $this->addSql('DROP TABLE IF EXISTS tags');
        $this->addSql('DROP INDEX IF EXISTS uniq_voyage_slug');
        $this->addSql('ALTER TABLE voyages DROP COLUMN IF EXISTS slug');
    }
}
