<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Public API v1: ApiKey entity for API key authentication.
 */
final class Version20260309000400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create api_key table for Public API v1 authentication';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE api_key (
            id BIGSERIAL NOT NULL,
            public_id UUID NOT NULL,
            customer_id BIGINT NOT NULL,
            key_hash VARCHAR(128) NOT NULL,
            name VARCHAR(100) NOT NULL,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            rate_limit_per_minute INT NOT NULL DEFAULT 60,
            last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');

        $this->addSql('COMMENT ON COLUMN api_key.public_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN api_key.last_used_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN api_key.created_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE UNIQUE INDEX uniq_api_key_public_id ON api_key (public_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_api_key_key_hash ON api_key (key_hash)');
        $this->addSql('CREATE INDEX idx_api_key_customer ON api_key (customer_id)');

        $this->addSql('ALTER TABLE api_key ADD CONSTRAINT fk_api_key_customer
            FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS api_key');
    }
}
