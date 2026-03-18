<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260318000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create route_performance_metric and optimization_strategy_comparison tables for learning system';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE route_performance_metric (
                id BIGSERIAL NOT NULL,
                public_id UUID NOT NULL,
                route_id BIGINT NOT NULL,
                customer_id BIGINT NOT NULL,
                optimizer_used VARCHAR(30) NOT NULL,
                planned_distance_km NUMERIC(10, 2) DEFAULT NULL,
                planned_duration_minutes INT DEFAULT NULL,
                total_stops INT NOT NULL,
                actual_distance_km NUMERIC(10, 2) DEFAULT NULL,
                actual_duration_minutes INT DEFAULT NULL,
                delivered_count INT NOT NULL,
                exception_count INT NOT NULL,
                skipped_count INT NOT NULL,
                delivery_success_rate NUMERIC(5, 1) DEFAULT NULL,
                km_saved NUMERIC(10, 2) DEFAULT NULL,
                time_saved_minutes INT DEFAULT NULL,
                plan_accuracy_percent NUMERIC(5, 1) DEFAULT NULL,
                tags JSON DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);

        $this->addSql('CREATE UNIQUE INDEX uniq_rpm_public_id ON route_performance_metric (public_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_rpm_route ON route_performance_metric (route_id)');
        $this->addSql('CREATE INDEX idx_rpm_customer_created ON route_performance_metric (customer_id, created_at)');
        $this->addSql('CREATE INDEX idx_rpm_optimizer ON route_performance_metric (optimizer_used)');
        $this->addSql('ALTER TABLE route_performance_metric ADD CONSTRAINT fk_rpm_route FOREIGN KEY (route_id) REFERENCES route_plan (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE route_performance_metric ADD CONSTRAINT fk_rpm_customer FOREIGN KEY (customer_id) REFERENCES customer (id)');
        $this->addSql('COMMENT ON COLUMN route_performance_metric.public_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN route_performance_metric.created_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql(<<<'SQL'
            CREATE TABLE optimization_strategy_comparison (
                id BIGSERIAL NOT NULL,
                public_id UUID NOT NULL,
                customer_id BIGINT DEFAULT NULL,
                strategy_a JSON NOT NULL,
                strategy_b JSON NOT NULL,
                chosen VARCHAR(10) NOT NULL,
                chosen_reason VARCHAR(255) DEFAULT NULL,
                actual_outcome JSON DEFAULT NULL,
                result_route_id BIGINT DEFAULT NULL,
                shipment_count INT NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                outcome_recorded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id)
            )
            SQL);

        $this->addSql('CREATE UNIQUE INDEX uniq_osc_public_id ON optimization_strategy_comparison (public_id)');
        $this->addSql('CREATE INDEX idx_osc_created_at ON optimization_strategy_comparison (created_at)');
        $this->addSql('ALTER TABLE optimization_strategy_comparison ADD CONSTRAINT fk_osc_customer FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE optimization_strategy_comparison ADD CONSTRAINT fk_osc_route FOREIGN KEY (result_route_id) REFERENCES route_plan (id) ON DELETE SET NULL');
        $this->addSql('COMMENT ON COLUMN optimization_strategy_comparison.public_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN optimization_strategy_comparison.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN optimization_strategy_comparison.outcome_recorded_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE optimization_strategy_comparison');
        $this->addSql('DROP TABLE route_performance_metric');
    }
}
