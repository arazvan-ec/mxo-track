<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop contact_email column from customer table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DO $$ BEGIN IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'customer\' AND column_name = \'contact_email\') THEN ALTER TABLE customer DROP COLUMN contact_email; END IF; END $$');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'customer\' AND column_name = \'contact_email\') THEN ALTER TABLE customer ADD COLUMN contact_email VARCHAR(180) DEFAULT NULL; END IF; END $$');
    }
}
