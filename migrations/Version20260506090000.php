<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260506090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cached factual IGDB metadata to games.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD igdb_developers JSON DEFAULT NULL, ADD igdb_publishers JSON DEFAULT NULL, ADD igdb_themes JSON DEFAULT NULL, ADD igdb_game_modes JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP igdb_developers, DROP igdb_publishers, DROP igdb_themes, DROP igdb_game_modes');
    }
}
