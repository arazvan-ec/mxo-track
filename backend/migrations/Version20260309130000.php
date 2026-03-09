<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * D.4 / F6.2: Push notification subscriptions for drivers.
 */
final class Version20260309130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create push_subscription table for driver push notifications';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE push_subscription (
                id BIGSERIAL PRIMARY KEY,
                public_id VARCHAR(26) NOT NULL,
                user_id BIGINT NOT NULL,
                endpoint VARCHAR(500) NOT NULL,
                auth_key VARCHAR(100) DEFAULT NULL,
                p256dh_key VARCHAR(100) DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT fk_push_subscription_user FOREIGN KEY (user_id) REFERENCES user_account (id) ON DELETE CASCADE
            )
        SQL);

        $this->addSql('CREATE UNIQUE INDEX uniq_push_subscription_public_id ON push_subscription (public_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_push_subscription_user_endpoint ON push_subscription (user_id, endpoint)');
        $this->addSql('CREATE INDEX idx_push_subscription_user ON push_subscription (user_id)');

        $this->addSql("COMMENT ON COLUMN push_subscription.public_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN push_subscription.created_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE push_subscription');
    }
}
