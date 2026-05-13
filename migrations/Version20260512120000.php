<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260512120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add earned trophies for synced trophy cabinets.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE earned_trophy (id VARCHAR(64) NOT NULL, user_id INT NOT NULL, trophy_id VARCHAR(120) NOT NULL, earned_at DATETIME NOT NULL, game_id VARCHAR(36) DEFAULT NULL, context JSON DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, INDEX IDX_4C6E9236A76ED395 (user_id), INDEX IDX_4C6E92362B46F3F8 (trophy_id), INDEX IDX_4C6E9236854CF4BD (earned_at), INDEX IDX_4C6E9236896DBBDE (updated_at), INDEX IDX_4C6E92365E237E06 (deleted_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE earned_trophy ADD CONSTRAINT FK_4C6E9236A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE earned_trophy DROP FOREIGN KEY FK_4C6E9236A76ED395');
        $this->addSql('DROP TABLE earned_trophy');
    }
}
