<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260518123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add More details metadata to games.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD release_year INT DEFAULT NULL, ADD priority VARCHAR(32) DEFAULT NULL, ADD developer VARCHAR(255) DEFAULT NULL, ADD publisher VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP release_year, DROP priority, DROP developer, DROP publisher');
    }
}
