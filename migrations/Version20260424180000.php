<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create reviews table for voyage ratings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reviews (
            id          SERIAL PRIMARY KEY,
            user_id     INT NOT NULL,
            voyage_id   INT NOT NULL,
            rating      SMALLINT NOT NULL DEFAULT 5,
            comment     TEXT DEFAULT NULL,
            created_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
            CONSTRAINT uq_review_user_voyage UNIQUE (user_id, voyage_id)
        )');
        $this->addSql('CREATE INDEX idx_reviews_voyage_id ON reviews (voyage_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS reviews');
    }
}
