<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add csv_import_run_id FK to shipment table.
 */
final class Version20260320000400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add csv_import_run_id nullable FK to shipment';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shipment ADD COLUMN csv_import_run_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE shipment ADD CONSTRAINT fk_shipment_csv_import_run FOREIGN KEY (csv_import_run_id) REFERENCES csv_import_run (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_shipment_csv_import_run ON shipment (csv_import_run_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shipment DROP CONSTRAINT fk_shipment_csv_import_run');
        $this->addSql('DROP INDEX idx_shipment_csv_import_run');
        $this->addSql('ALTER TABLE shipment DROP COLUMN csv_import_run_id');
    }
}
