<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260422133008 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
UPDATE refund_requests rr
SET reclamation_id = (
    SELECT r.id
    FROM reclamations r
    WHERE r.user_id = rr.requester_id
    ORDER BY r.created_at DESC, r.id DESC
    LIMIT 1
)
WHERE rr.reclamation_id IS NULL
  AND EXISTS (
      SELECT 1
      FROM reclamations r
      WHERE r.user_id = rr.requester_id
  )
SQL
        );
        $this->addSql('ALTER TABLE refund_requests ALTER reclamation_id SET NOT NULL');
        $this->addSql('ALTER TABLE users ALTER roles TYPE JSON');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE refund_requests ALTER reclamation_id DROP NOT NULL');
        $this->addSql('ALTER TABLE users ALTER roles TYPE JSONB');
    }
}
