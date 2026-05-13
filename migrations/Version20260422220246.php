<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260422220246 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE game (id VARCHAR(36) NOT NULL, title VARCHAR(255) NOT NULL, status VARCHAR(32) NOT NULL, rating INT DEFAULT NULL, play_time_hours NUMERIC(6, 1) DEFAULT NULL, review LONGTEXT NOT NULL, platform VARCHAR(120) NOT NULL, tags JSON NOT NULL, finished_at DATE DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_232B318CA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE log_entry (id VARCHAR(36) NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, game_id VARCHAR(36) NOT NULL, INDEX IDX_B5F762DE48FD905 (game_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE sync_token (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, token_hash VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, last_used_at DATETIME DEFAULT NULL, revoked_at DATETIME DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_B9062B96B3BC57DA (token_hash), INDEX IDX_B9062B96A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) DEFAULT NULL, display_name VARCHAR(180) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE game ADD CONSTRAINT FK_232B318CA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE log_entry ADD CONSTRAINT FK_B5F762DE48FD905 FOREIGN KEY (game_id) REFERENCES game (id)');
        $this->addSql('ALTER TABLE sync_token ADD CONSTRAINT FK_B9062B96A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE game DROP FOREIGN KEY FK_232B318CA76ED395');
        $this->addSql('ALTER TABLE log_entry DROP FOREIGN KEY FK_B5F762DE48FD905');
        $this->addSql('ALTER TABLE sync_token DROP FOREIGN KEY FK_B9062B96A76ED395');
        $this->addSql('DROP TABLE game');
        $this->addSql('DROP TABLE log_entry');
        $this->addSql('DROP TABLE sync_token');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
