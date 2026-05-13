<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260505143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cached IGDB time-to-beat metadata to games.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD igdb_ttb_hastily_seconds INT DEFAULT NULL, ADD igdb_ttb_normally_seconds INT DEFAULT NULL, ADD igdb_ttb_completely_seconds INT DEFAULT NULL, ADD igdb_ttb_count INT DEFAULT NULL, ADD igdb_ttb_updated_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP igdb_ttb_hastily_seconds, DROP igdb_ttb_normally_seconds, DROP igdb_ttb_completely_seconds, DROP igdb_ttb_count, DROP igdb_ttb_updated_at');
    }
}
