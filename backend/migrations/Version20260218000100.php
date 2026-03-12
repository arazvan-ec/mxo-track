<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260218000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fase 3: customer_vehicle sin public_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_customer_vehicle_public_id');
        $this->addSql('DO $$ BEGIN IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'customer_vehicle\' AND column_name = \'public_id\') THEN ALTER TABLE customer_vehicle DROP COLUMN public_id; END IF; END $$');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'customer_vehicle\' AND column_name = \'public_id\') THEN ALTER TABLE customer_vehicle ADD COLUMN public_id UUID DEFAULT NULL; END IF; END $$');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_customer_vehicle_public_id ON customer_vehicle (public_id)');
    }
}
