<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028001508 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create webhook deliveries and attempt journal';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE webhook_deliveries (
            id VARCHAR(32) NOT NULL PRIMARY KEY,
            event_id VARCHAR(32) NOT NULL,
            event_name VARCHAR(100) NOT NULL,
            endpoint VARCHAR(100) NOT NULL,
            body TEXT NOT NULL,
            status VARCHAR(20) NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            last_http_status INT DEFAULT NULL,
            last_error VARCHAR(100) DEFAULT NULL,
            created_at BIGINT NOT NULL,
            delivered_at BIGINT DEFAULT NULL
        )');
        $this->addSql('CREATE INDEX idx_webhook_created ON webhook_deliveries (created_at)');
        $this->addSql('CREATE TABLE webhook_attempts (
            delivery_id VARCHAR(32) NOT NULL,
            attempt_no INT NOT NULL,
            attempted_at BIGINT NOT NULL,
            http_status INT DEFAULT NULL,
            error VARCHAR(100) DEFAULT NULL,
            duration_ms INT NOT NULL,
            PRIMARY KEY (delivery_id, attempt_no),
            CONSTRAINT fk_webhook_attempt_delivery FOREIGN KEY (delivery_id) REFERENCES webhook_deliveries (id) ON DELETE CASCADE
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE webhook_attempts');
        $this->addSql('DROP TABLE webhook_deliveries');
    }
}
