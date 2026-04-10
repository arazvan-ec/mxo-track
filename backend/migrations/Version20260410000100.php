<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create reoptimization_policy table for per-customer reoptimization configuration.
 */
final class Version20260410000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create reoptimization_policy table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE reoptimization_policy (
                id BIGSERIAL PRIMARY KEY,
                public_id VARCHAR(26) NOT NULL,
                customer_id BIGINT NOT NULL,
                triggers JSON NOT NULL DEFAULT \'[]\',
                delay_threshold_minutes INT NOT NULL DEFAULT 30,
                consecutive_exception_threshold INT NOT NULL DEFAULT 2,
                cooldown_minutes INT NOT NULL DEFAULT 10,
                enabled BOOLEAN NOT NULL DEFAULT TRUE,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT fk_reopt_policy_customer FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE CASCADE,
                CONSTRAINT uniq_reoptimization_policy_public_id UNIQUE (public_id),
                CONSTRAINT uniq_reoptimization_policy_customer UNIQUE (customer_id)
            )
        ');

        $this->addSql('COMMENT ON COLUMN reoptimization_policy.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN reoptimization_policy.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS reoptimization_policy');
    }
}
