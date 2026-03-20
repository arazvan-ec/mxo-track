<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create projection tables for event-sourced read models.
 */
final class Version20260320000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add route_current_state and stop_current_status projection tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE route_current_state (
                route_id BIGINT NOT NULL,
                public_id VARCHAR(26) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'PLANNED',
                name VARCHAR(140) NOT NULL DEFAULT '',
                driver_user_id BIGINT DEFAULT NULL,
                vehicle_id BIGINT DEFAULT NULL,
                customer_id BIGINT DEFAULT NULL,
                total_distance_km NUMERIC(8,2) DEFAULT NULL,
                estimated_duration_minutes INT DEFAULT NULL,
                total_stops INT NOT NULL DEFAULT 0,
                delivered_stops INT NOT NULL DEFAULT 0,
                exception_stops INT NOT NULL DEFAULT 0,
                pending_stops INT NOT NULL DEFAULT 0,
                skipped_stops INT NOT NULL DEFAULT 0,
                started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
                PRIMARY KEY (route_id)
            )
        SQL);

        $this->addSql('CREATE UNIQUE INDEX uniq_rcs_public_id ON route_current_state (public_id)');
        $this->addSql('CREATE INDEX idx_rcs_status ON route_current_state (status)');
        $this->addSql('CREATE INDEX idx_rcs_customer ON route_current_state (customer_id)');
        $this->addSql('CREATE INDEX idx_rcs_driver ON route_current_state (driver_user_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE stop_current_status (
                stop_id BIGINT NOT NULL,
                route_id BIGINT NOT NULL,
                public_id VARCHAR(36) DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
                sequence INT NOT NULL DEFAULT 0,
                is_origin BOOLEAN NOT NULL DEFAULT FALSE,
                delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                exception_code VARCHAR(30) DEFAULT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
                PRIMARY KEY (stop_id)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_scs_route ON stop_current_status (route_id)');
        $this->addSql('CREATE INDEX idx_scs_status ON stop_current_status (status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS stop_current_status');
        $this->addSql('DROP TABLE IF EXISTS route_current_state');
    }
}
