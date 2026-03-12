<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fase 4B: enriquecer RouteStop (coords, recipient), Shipment (delivery data), Customer (contact), Route (customer_id)';
    }

    public function up(Schema $schema): void
    {
        // RouteStop: add coordinates and recipient info
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'route_stop\' AND column_name = \'latitude\') THEN ALTER TABLE route_stop ADD COLUMN latitude DOUBLE PRECISION DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'route_stop\' AND column_name = \'longitude\') THEN ALTER TABLE route_stop ADD COLUMN longitude DOUBLE PRECISION DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'route_stop\' AND column_name = \'recipient_name\') THEN ALTER TABLE route_stop ADD COLUMN recipient_name VARCHAR(150) DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'route_stop\' AND column_name = \'recipient_phone\') THEN ALTER TABLE route_stop ADD COLUMN recipient_phone VARCHAR(30) DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'route_stop\' AND column_name = \'notes\') THEN ALTER TABLE route_stop ADD COLUMN notes TEXT DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'route_stop\' AND column_name = \'shipment_id\') THEN ALTER TABLE route_stop ADD COLUMN shipment_id BIGINT DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = \'fk_route_stop_shipment\') THEN ALTER TABLE route_stop ADD CONSTRAINT fk_route_stop_shipment FOREIGN KEY (shipment_id) REFERENCES shipment (id) ON DELETE SET NULL; END IF; END $$');

        // Shipment: add delivery data
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'shipment\' AND column_name = \'recipient_name\') THEN ALTER TABLE shipment ADD COLUMN recipient_name VARCHAR(150) DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'shipment\' AND column_name = \'recipient_phone\') THEN ALTER TABLE shipment ADD COLUMN recipient_phone VARCHAR(30) DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'shipment\' AND column_name = \'address\') THEN ALTER TABLE shipment ADD COLUMN address VARCHAR(255) DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'shipment\' AND column_name = \'latitude\') THEN ALTER TABLE shipment ADD COLUMN latitude DOUBLE PRECISION DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'shipment\' AND column_name = \'longitude\') THEN ALTER TABLE shipment ADD COLUMN longitude DOUBLE PRECISION DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'shipment\' AND column_name = \'notes\') THEN ALTER TABLE shipment ADD COLUMN notes TEXT DEFAULT NULL; END IF; END $$');

        // Customer: add contact info and active flag
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'customer\' AND column_name = \'address\') THEN ALTER TABLE customer ADD COLUMN address VARCHAR(255) DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'customer\' AND column_name = \'contact_email\') THEN ALTER TABLE customer ADD COLUMN contact_email VARCHAR(180) DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'customer\' AND column_name = \'contact_phone\') THEN ALTER TABLE customer ADD COLUMN contact_phone VARCHAR(30) DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'customer\' AND column_name = \'is_active\') THEN ALTER TABLE customer ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT TRUE; END IF; END $$');

        // Route: add customer_id for linking routes to warehouses
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'route_plan\' AND column_name = \'customer_id\') THEN ALTER TABLE route_plan ADD COLUMN customer_id BIGINT DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = \'fk_route_customer\') THEN ALTER TABLE route_plan ADD CONSTRAINT fk_route_customer FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE SET NULL; END IF; END $$');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE route_stop DROP CONSTRAINT IF EXISTS fk_route_stop_shipment;');
        $this->addSql('ALTER TABLE route_stop DROP COLUMN latitude, DROP COLUMN longitude, DROP COLUMN recipient_name, DROP COLUMN recipient_phone, DROP COLUMN notes, DROP COLUMN shipment_id;');

        $this->addSql('ALTER TABLE shipment DROP COLUMN recipient_name, DROP COLUMN recipient_phone, DROP COLUMN address, DROP COLUMN latitude, DROP COLUMN longitude, DROP COLUMN notes;');

        $this->addSql('ALTER TABLE customer DROP COLUMN address, DROP COLUMN contact_email, DROP COLUMN contact_phone, DROP COLUMN is_active;');

        $this->addSql('ALTER TABLE route_plan DROP CONSTRAINT IF EXISTS fk_route_customer;');
        $this->addSql('ALTER TABLE route_plan DROP COLUMN customer_id;');
    }
}
