<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422174000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add payment_reference to reservations and reservation_id to refund_requests';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservations ADD payment_reference VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE refund_requests ADD reservation_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refund_requests DROP reservation_id');
        $this->addSql('ALTER TABLE reservations DROP payment_reference');
    }
}
