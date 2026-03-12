<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove ROLE_OPERATOR (migrate to ROLE_ADMIN) and add name column to user_account';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE user_account SET roles = REPLACE(roles::text, 'ROLE_OPERATOR', 'ROLE_ADMIN')::jsonb WHERE roles::text LIKE '%ROLE_OPERATOR%'");
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'user_account\' AND column_name = \'name\') THEN ALTER TABLE user_account ADD COLUMN name VARCHAR(150) DEFAULT NULL; END IF; END $$');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_account DROP COLUMN name');
    }
}
