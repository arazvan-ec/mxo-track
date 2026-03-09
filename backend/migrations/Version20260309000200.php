<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * F5.1: Driver availability / weekly schedule management.
 */
final class Version20260309000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create driver_availability table for weekly schedule management';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE driver_availability (
                id BIGSERIAL PRIMARY KEY,
                public_id VARCHAR(26) NOT NULL UNIQUE,
                driver_id BIGINT NOT NULL REFERENCES "user_account"(id) ON DELETE CASCADE,
                day_of_week SMALLINT NOT NULL,
                start_time VARCHAR(5) NOT NULL,
                end_time VARCHAR(5) NOT NULL,
                is_available BOOLEAN NOT NULL DEFAULT true,
                notes TEXT DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
            SQL);

        $this->addSql('CREATE INDEX idx_driver_availability_driver ON driver_availability (driver_id)');
        $this->addSql('CREATE INDEX idx_driver_availability_day ON driver_availability (day_of_week)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS driver_availability');
    }
}
