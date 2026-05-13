<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260513170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add game ownership type for digital and physical library tracking.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD ownership_type VARCHAR(16) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP ownership_type');
    }
}
