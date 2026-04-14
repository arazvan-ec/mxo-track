<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create user_preference table for per-user UI preferences (widget default mode, etc.).
 */
final class Version20260414010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_preference table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE user_preference (
                id BIGSERIAL PRIMARY KEY,
                public_id VARCHAR(26) NOT NULL,
                user_id BIGINT NOT NULL,
                widget_default_mode VARCHAR(16) NOT NULL DEFAULT \'expanded\',
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT fk_user_preference_user FOREIGN KEY (user_id) REFERENCES user_account (id) ON DELETE CASCADE,
                CONSTRAINT uniq_user_preference_public_id UNIQUE (public_id),
                CONSTRAINT uniq_user_preference_user UNIQUE (user_id),
                CONSTRAINT chk_user_preference_widget_mode CHECK (widget_default_mode IN (\'expanded\', \'collapsed\'))
            )
        ');

        $this->addSql('COMMENT ON COLUMN user_preference.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_preference.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS user_preference');
    }
}
