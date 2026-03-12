<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create notification_log, recipient_action, notification_preference tables.
 * Add notification_quota to customer. Drop recipient_notification.
 */
final class Version20260312000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notification_log, recipient_action, notification_preference tables; add customer.notification_quota; drop recipient_notification';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE notification_log (
                id BIGSERIAL PRIMARY KEY,
                public_id UUID NOT NULL,
                shipment_id BIGINT NOT NULL REFERENCES shipment(id),
                customer_id BIGINT NOT NULL REFERENCES customer(id),
                channel VARCHAR(20) NOT NULL,
                trigger_type VARCHAR(30) NOT NULL,
                recipient_phone VARCHAR(20) NOT NULL,
                message_content TEXT NOT NULL,
                status VARCHAR(20) NOT NULL,
                provider_response JSON NOT NULL DEFAULT '{}',
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);

        $this->addSql('CREATE UNIQUE INDEX uniq_notification_log_public_id ON notification_log (public_id)');
        $this->addSql('CREATE INDEX idx_notif_dedup ON notification_log (shipment_id, trigger_type, channel)');
        $this->addSql('CREATE INDEX idx_notif_throttle ON notification_log (recipient_phone, channel, created_at)');
        $this->addSql('CREATE INDEX idx_notif_quota ON notification_log (customer_id, channel, created_at)');

        $this->addSql(<<<'SQL'
            CREATE TABLE recipient_action (
                id BIGSERIAL PRIMARY KEY,
                public_id UUID NOT NULL,
                shipment_id BIGINT NOT NULL REFERENCES shipment(id),
                action_type VARCHAR(30) NOT NULL,
                payload JSON NOT NULL DEFAULT '{}',
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);

        $this->addSql('CREATE UNIQUE INDEX uniq_recipient_action_public_id ON recipient_action (public_id)');
        $this->addSql('CREATE INDEX idx_recipient_action_shipment ON recipient_action (shipment_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE notification_preference (
                id BIGSERIAL PRIMARY KEY,
                public_id UUID NOT NULL,
                customer_id BIGINT NOT NULL REFERENCES customer(id),
                trigger_type VARCHAR(30) NOT NULL,
                channel VARCHAR(20) NOT NULL,
                enabled BOOLEAN NOT NULL DEFAULT TRUE,
                message_template TEXT DEFAULT NULL,
                timing_config JSON NOT NULL DEFAULT '{}',
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);

        $this->addSql('CREATE UNIQUE INDEX uniq_notification_preference_public_id ON notification_preference (public_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_notif_pref_customer_trigger_channel ON notification_preference (customer_id, trigger_type, channel)');

        $this->addSql('ALTER TABLE customer ADD COLUMN notification_quota INTEGER DEFAULT NULL');

        $this->addSql('DROP TABLE IF EXISTS recipient_notification');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS notification_log');
        $this->addSql('DROP TABLE IF EXISTS recipient_action');
        $this->addSql('DROP TABLE IF EXISTS notification_preference');
        $this->addSql('ALTER TABLE customer DROP COLUMN IF EXISTS notification_quota');
    }
}
