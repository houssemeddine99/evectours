<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260412115755 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
       // 1. On ajoute la colonne en acceptant le NULL temporairement
    $this->addSql('ALTER TABLE users ADD roles JSONB DEFAULT NULL');
    
    // 2. On remplit les lignes existantes avec un tableau vide JSON
    $this->addSql('UPDATE users SET roles = \'[]\' WHERE roles IS NULL');
    
    // 3. Maintenant on peut forcer le NOT NULL
    $this->addSql('ALTER TABLE users ALTER COLUMN roles SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE users DROP roles');
    }
}
