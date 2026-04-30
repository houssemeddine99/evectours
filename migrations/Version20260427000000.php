<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260427000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restored placeholder — migration was executed but file was lost; schema already applied.';
    }

    public function up(Schema $schema): void
    {
        // already applied
    }

    public function down(Schema $schema): void
    {
        // no rollback needed
    }
}
