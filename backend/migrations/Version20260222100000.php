<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tracking_token column to shipment table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'shipment\' AND column_name = \'tracking_token\') THEN ALTER TABLE shipment ADD COLUMN tracking_token VARCHAR(20) DEFAULT NULL; END IF; END $$');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_shipment_tracking_token ON shipment (tracking_token)');

        // Generate tokens for existing shipments
        $this->addSql(<<<'SQL'
            UPDATE shipment
            SET tracking_token = 'TRK-' || UPPER(SUBSTR(MD5(RANDOM()::TEXT), 1, 4)) || '-' || UPPER(SUBSTR(MD5(RANDOM()::TEXT), 1, 4))
            WHERE tracking_token IS NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_shipment_tracking_token');
        $this->addSql('ALTER TABLE shipment DROP COLUMN tracking_token');
    }
}
