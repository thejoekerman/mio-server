<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260615120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store bounded sync deletion markers and a per-user recovery cursor floor.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD minimum_supported_cursor INT UNSIGNED DEFAULT 0 NOT NULL');
        $this->addSql('CREATE TABLE sync_deletion (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, entity_type VARCHAR(32) NOT NULL, entity_id VARCHAR(255) NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME NOT NULL, revision INT UNSIGNED NOT NULL, INDEX IDX_6CCF670BA76ED395 (user_id), UNIQUE INDEX sync_deletion_identity (user_id, entity_type, entity_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE sync_deletion ADD CONSTRAINT FK_F195E7BBA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql("INSERT INTO sync_deletion (user_id, entity_type, entity_id, updated_at, deleted_at, revision) SELECT game.user_id, 'log', log_entry.id, log_entry.updated_at, CURRENT_TIMESTAMP, log_entry.revision FROM log_entry INNER JOIN journey ON journey.id = log_entry.journey_id INNER JOIN game ON game.id = journey.game_id WHERE log_entry.deleted_at IS NOT NULL");
        $this->addSql("INSERT INTO sync_deletion (user_id, entity_type, entity_id, updated_at, deleted_at, revision) SELECT game.user_id, 'journey', journey.id, journey.updated_at, CURRENT_TIMESTAMP, journey.revision FROM journey INNER JOIN game ON game.id = journey.game_id WHERE journey.deleted_at IS NOT NULL");
        $this->addSql("INSERT INTO sync_deletion (user_id, entity_type, entity_id, updated_at, deleted_at, revision) SELECT game.user_id, 'game', game.id, game.updated_at, CURRENT_TIMESTAMP, game.revision FROM game WHERE game.deleted_at IS NOT NULL");
        $this->addSql("INSERT INTO sync_deletion (user_id, entity_type, entity_id, updated_at, deleted_at, revision) SELECT earned_trophy.user_id, 'earnedTrophy', SUBSTRING(earned_trophy.id, LENGTH(earned_trophy.user_id) + 2), earned_trophy.updated_at, CURRENT_TIMESTAMP, earned_trophy.revision FROM earned_trophy WHERE earned_trophy.deleted_at IS NOT NULL");
        $this->addSql('DELETE FROM log_entry WHERE deleted_at IS NOT NULL');
        $this->addSql('DELETE FROM journey WHERE deleted_at IS NOT NULL');
        $this->addSql('DELETE FROM game WHERE deleted_at IS NOT NULL');
        $this->addSql('DELETE FROM earned_trophy WHERE deleted_at IS NOT NULL');
        $this->addSql('ALTER TABLE `user` CHANGE minimum_supported_cursor minimum_supported_cursor INT UNSIGNED NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sync_deletion');
        $this->addSql('ALTER TABLE `user` DROP minimum_supported_cursor');
    }
}
