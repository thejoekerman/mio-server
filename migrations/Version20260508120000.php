<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add paused nudge dates to games.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD paused_at DATE DEFAULT NULL, ADD nudge_at DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP paused_at, DROP nudge_at');
    }
}
