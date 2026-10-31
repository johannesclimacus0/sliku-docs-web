<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028232834 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user notifications and read state';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notifications (
            id VARCHAR(32) NOT NULL,
            user_id INT NOT NULL,
            type VARCHAR(100) NOT NULL,
            title VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            data TEXT NOT NULL,
            created_at BIGINT NOT NULL,
            read_at BIGINT DEFAULT NULL,
            PRIMARY KEY (id, user_id),
            CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX idx_notification_user_unread ON notifications (user_id, read_at, created_at, id)');
        $this->addSql('CREATE INDEX idx_notification_user_created ON notifications (user_id, created_at, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notifications');
    }
}
