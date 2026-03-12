<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * F2.1: RecipientNotification entity for tracking SMS/notifications sent to delivery recipients.
 */
final class Version20260309000300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create recipient_notification table for ETA notification tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS recipient_notification (
                id BIGSERIAL NOT NULL,
                public_id UUID NOT NULL,
                shipment_id BIGINT NOT NULL,
                channel VARCHAR(20) NOT NULL,
                template_name VARCHAR(60) NOT NULL,
                recipient VARCHAR(50) NOT NULL,
                status VARCHAR(20) NOT NULL,
                sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = \'fk_recipient_notification_shipment\') THEN ALTER TABLE recipient_notification ADD CONSTRAINT fk_recipient_notification_shipment FOREIGN KEY (shipment_id) REFERENCES shipment (id) ON DELETE CASCADE; END IF; END $$');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_recipient_notification_public_id ON recipient_notification (public_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_recipient_notification_shipment ON recipient_notification (shipment_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_recipient_notification_status ON recipient_notification (status)');

        $this->addSql("COMMENT ON COLUMN recipient_notification.sent_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN recipient_notification.created_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS recipient_notification');
    }
}
