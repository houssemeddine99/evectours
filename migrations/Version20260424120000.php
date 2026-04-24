<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add response_deadline to reclamations and approved_amount to refund_requests';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reclamations ADD response_deadline TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE refund_requests ADD approved_amount NUMERIC(10,2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reclamations DROP COLUMN IF EXISTS response_deadline');
        $this->addSql('ALTER TABLE refund_requests DROP COLUMN IF EXISTS approved_amount');
    }
}
