<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Public API v1: WebhookEndpoint entity for customer-managed webhook subscriptions.
 */
final class Version20260309000500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create webhook_endpoint table for Public API v1 webhook management';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE webhook_endpoint (
            id BIGSERIAL NOT NULL,
            public_id UUID NOT NULL,
            customer_id BIGINT NOT NULL,
            url VARCHAR(500) NOT NULL,
            events JSON NOT NULL DEFAULT \'[]\',
            secret VARCHAR(128) NOT NULL,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');

        $this->addSql('COMMENT ON COLUMN webhook_endpoint.public_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN webhook_endpoint.created_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE UNIQUE INDEX uniq_webhook_endpoint_public_id ON webhook_endpoint (public_id)');
        $this->addSql('CREATE INDEX idx_webhook_endpoint_customer ON webhook_endpoint (customer_id)');

        $this->addSql('ALTER TABLE webhook_endpoint ADD CONSTRAINT fk_webhook_endpoint_customer
            FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS webhook_endpoint');
    }
}
