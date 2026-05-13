<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260505090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional IGDB metadata fields to games.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD igdb_id INT DEFAULT NULL, ADD igdb_url VARCHAR(255) DEFAULT NULL, ADD cover_url VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP igdb_id, DROP igdb_url, DROP cover_url');
    }
}
