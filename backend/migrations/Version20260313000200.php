<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create route_optimization_log table for persisting optimization process logs.
 */
final class Version20260313000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create route_optimization_log table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE route_optimization_log (
            id BIGSERIAL NOT NULL,
            public_id UUID NOT NULL,
            route_id BIGINT DEFAULT NULL,
            customer_id BIGINT DEFAULT NULL,
            operation VARCHAR(30) NOT NULL,
            optimizer_used VARCHAR(30) NOT NULL,
            input_summary JSON NOT NULL,
            steps JSON NOT NULL,
            result_summary JSON NOT NULL,
            duration_ms INT NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('COMMENT ON COLUMN route_optimization_log.public_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN route_optimization_log.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX uniq_route_opt_log_public_id ON route_optimization_log (public_id)');
        $this->addSql('CREATE INDEX idx_route_opt_log_created_at ON route_optimization_log (created_at)');
        $this->addSql('CREATE INDEX idx_route_opt_log_operation ON route_optimization_log (operation)');
        $this->addSql('ALTER TABLE route_optimization_log ADD CONSTRAINT fk_route_opt_log_route FOREIGN KEY (route_id) REFERENCES route_plan (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE route_optimization_log ADD CONSTRAINT fk_route_opt_log_customer FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE route_optimization_log');
    }
}
