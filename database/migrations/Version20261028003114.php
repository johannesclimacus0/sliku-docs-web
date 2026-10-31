<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028003114 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user activity journal';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_activity (id VARCHAR(32) NOT NULL PRIMARY KEY, user_id INT NOT NULL, action VARCHAR(100) NOT NULL, created_at BIGINT NOT NULL, CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE)');
        $this->addSql('CREATE INDEX idx_activity_user_created ON user_activity (user_id, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_activity');
    }
}
