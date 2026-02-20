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
        $this->addSql('ALTER TABLE customer_vehicle DROP COLUMN public_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_vehicle ADD public_id UUID DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_customer_vehicle_public_id ON customer_vehicle (public_id)');
    }
}
