<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260614120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Split personal play data into journeys and prepare canonical cursor-based sync.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD sync_revision INT UNSIGNED DEFAULT 0 NOT NULL, CHANGE ai_usage ai_usage TINYINT NOT NULL');
        $this->addSql('ALTER TABLE game ADD developers JSON DEFAULT NULL, ADD publishers JSON DEFAULT NULL, ADD genres JSON DEFAULT NULL, ADD themes JSON DEFAULT NULL, ADD game_modes JSON DEFAULT NULL, ADD cover JSON DEFAULT NULL, ADD external_references JSON DEFAULT NULL, ADD playtime_estimates JSON DEFAULT NULL, ADD metadata_reviewed_at DATETIME DEFAULT NULL, ADD revision INT UNSIGNED DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE game SET developers = COALESCE(IF(developer IS NULL, NULL, JSON_ARRAY(developer)), igdb_developers, JSON_ARRAY()), publishers = COALESCE(IF(publisher IS NULL, NULL, JSON_ARRAY(publisher)), igdb_publishers, JSON_ARRAY()), genres = JSON_ARRAY(), themes = COALESCE(igdb_themes, JSON_ARRAY()), game_modes = COALESCE(igdb_game_modes, JSON_ARRAY()), cover = IF(cover_url IS NULL, NULL, JSON_OBJECT(\'url\', cover_url, \'source\', JSON_OBJECT(\'provider\', \'manual\', \'pageUrl\', NULL))), external_references = JSON_ARRAY()');
        $this->addSql('ALTER TABLE game MODIFY developers JSON NOT NULL, MODIFY publishers JSON NOT NULL, MODIFY genres JSON NOT NULL, MODIFY themes JSON NOT NULL, MODIFY game_modes JSON NOT NULL, MODIFY external_references JSON NOT NULL');

        $this->addSql('CREATE TABLE journey (id VARCHAR(80) NOT NULL, game_id VARCHAR(36) NOT NULL, status VARCHAR(32) NOT NULL, platform VARCHAR(120) NOT NULL, ownership_type VARCHAR(16) DEFAULT NULL, priority VARCHAR(32) DEFAULT NULL, rating INT DEFAULT NULL, review LONGTEXT NOT NULL, play_time_hours NUMERIC(6, 1) DEFAULT NULL, started_at DATE DEFAULT NULL, finished_at DATE DEFAULT NULL, paused_at DATE DEFAULT NULL, nudge_at DATE DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, revision INT UNSIGNED DEFAULT 0 NOT NULL, INDEX IDX_C816C6A2E48FD905 (game_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('INSERT INTO journey (id, game_id, status, platform, ownership_type, priority, rating, review, play_time_hours, started_at, finished_at, paused_at, nudge_at, created_at, updated_at, deleted_at, revision) SELECT CONCAT(id, \':initial-journey\'), id, status, platform, ownership_type, priority, rating, review, play_time_hours, NULL, finished_at, paused_at, nudge_at, created_at, updated_at, deleted_at, 0 FROM game');
        $this->addSql('ALTER TABLE journey ADD CONSTRAINT FK_2B7C7D6348FD905 FOREIGN KEY (game_id) REFERENCES game (id)');

        $this->addSql('ALTER TABLE log_entry ADD journey_id VARCHAR(80) DEFAULT NULL, ADD revision INT UNSIGNED DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE log_entry SET journey_id = CONCAT(game_id, \':initial-journey\')');
        $this->addSql('ALTER TABLE log_entry DROP FOREIGN KEY FK_B5F762DE48FD905');
        $this->addSql('DROP INDEX IDX_B5F762DE48FD905 ON log_entry');
        $this->addSql('ALTER TABLE log_entry DROP game_id, MODIFY journey_id VARCHAR(80) NOT NULL');
        $this->addSql('ALTER TABLE log_entry ADD CONSTRAINT FK_B5F762DE71B13D5 FOREIGN KEY (journey_id) REFERENCES journey (id)');
        $this->addSql('CREATE INDEX IDX_B5F762DD5C9896F ON log_entry (journey_id)');

        $this->addSql('ALTER TABLE earned_trophy ADD revision INT UNSIGNED DEFAULT 0 NOT NULL');
        $this->addSql('DROP INDEX IDX_4C6E92362B46F3F8 ON earned_trophy');
        $this->addSql('DROP INDEX IDX_4C6E92365E237E06 ON earned_trophy');
        $this->addSql('DROP INDEX IDX_4C6E9236854CF4BD ON earned_trophy');
        $this->addSql('DROP INDEX IDX_4C6E9236896DBBDE ON earned_trophy');
        $this->addSql('ALTER TABLE earned_trophy RENAME INDEX IDX_4C6E9236A76ED395 TO IDX_F11F78E7A76ED395');
        $this->addSql('ALTER TABLE game DROP status, DROP rating, DROP play_time_hours, DROP review, DROP platform, DROP ownership_type, DROP igdb_id, DROP igdb_url, DROP cover_url, DROP igdb_ttb_hastily_seconds, DROP igdb_ttb_normally_seconds, DROP igdb_ttb_completely_seconds, DROP igdb_ttb_count, DROP igdb_ttb_updated_at, DROP igdb_developers, DROP igdb_publishers, DROP igdb_themes, DROP igdb_game_modes, DROP priority, DROP developer, DROP publisher, DROP finished_at, DROP paused_at, DROP nudge_at, DROP developer_updated_at, DROP publisher_updated_at, DROP release_year_updated_at, DROP priority_updated_at');
        $this->addSql('ALTER TABLE `user` CHANGE sync_revision sync_revision INT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE game CHANGE revision revision INT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE journey CHANGE revision revision INT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE log_entry CHANGE revision revision INT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE earned_trophy CHANGE revision revision INT UNSIGNED NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('The 3.0 canonical game/journey split cannot be safely reversed.');
    }
}
