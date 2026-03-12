<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create route_plan_template table for reusable route templates (D.2 / F6.4).
 */
final class Version20260309120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create route_plan_template table for reusable route plan templates';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS route_plan_template (
                id BIGSERIAL NOT NULL,
                public_id UUID NOT NULL,
                customer_id BIGINT NOT NULL,
                name VARCHAR(100) NOT NULL,
                template_data JSON NOT NULL DEFAULT '[]',
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_route_plan_template_public_id ON route_plan_template (public_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_route_plan_template_customer ON route_plan_template (customer_id)');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = \'fk_route_plan_template_customer\') THEN ALTER TABLE route_plan_template ADD CONSTRAINT fk_route_plan_template_customer FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE; END IF; END $$');

        $this->addSql("COMMENT ON COLUMN route_plan_template.public_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN route_plan_template.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN route_plan_template.updated_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS route_plan_template');
    }
}
