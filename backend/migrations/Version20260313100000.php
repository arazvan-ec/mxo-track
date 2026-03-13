<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260313100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create route_snapshot table for persisting optimization results and progress';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE route_snapshot (
                id BIGSERIAL NOT NULL,
                route_id BIGINT NOT NULL,
                polyline TEXT DEFAULT NULL,
                original_polyline TEXT DEFAULT NULL,
                actual_polyline TEXT DEFAULT NULL,
                distance_before_km NUMERIC(10, 2) DEFAULT NULL,
                distance_after_km NUMERIC(10, 2) DEFAULT NULL,
                savings_percent NUMERIC(5, 1) DEFAULT NULL,
                driving_time_minutes INT DEFAULT NULL,
                delivery_time_minutes INT DEFAULT NULL,
                total_time_minutes INT DEFAULT NULL,
                original_stop_order JSON DEFAULT NULL,
                stop_states JSON DEFAULT NULL,
                capacity_validation JSON DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('COMMENT ON COLUMN route_snapshot.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN route_snapshot.updated_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE INDEX idx_route_snapshot_route ON route_snapshot (route_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_route_snapshot_route ON route_snapshot (route_id)');

        $this->addSql('ALTER TABLE route_snapshot ADD CONSTRAINT fk_route_snapshot_route FOREIGN KEY (route_id) REFERENCES route_plan (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE route_snapshot');
    }
}
