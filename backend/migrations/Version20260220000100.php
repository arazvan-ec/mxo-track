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
        $this->addSql('ALTER TABLE route_stop ADD COLUMN latitude DOUBLE PRECISION DEFAULT NULL;');
        $this->addSql('ALTER TABLE route_stop ADD COLUMN longitude DOUBLE PRECISION DEFAULT NULL;');
        $this->addSql('ALTER TABLE route_stop ADD COLUMN recipient_name VARCHAR(150) DEFAULT NULL;');
        $this->addSql('ALTER TABLE route_stop ADD COLUMN recipient_phone VARCHAR(30) DEFAULT NULL;');
        $this->addSql('ALTER TABLE route_stop ADD COLUMN notes TEXT DEFAULT NULL;');
        $this->addSql('ALTER TABLE route_stop ADD COLUMN shipment_id BIGINT DEFAULT NULL;');
        $this->addSql('ALTER TABLE route_stop ADD CONSTRAINT fk_route_stop_shipment FOREIGN KEY (shipment_id) REFERENCES shipment (id) ON DELETE SET NULL;');

        // Shipment: add delivery data
        $this->addSql('ALTER TABLE shipment ADD COLUMN recipient_name VARCHAR(150) DEFAULT NULL;');
        $this->addSql('ALTER TABLE shipment ADD COLUMN recipient_phone VARCHAR(30) DEFAULT NULL;');
        $this->addSql('ALTER TABLE shipment ADD COLUMN address VARCHAR(255) DEFAULT NULL;');
        $this->addSql('ALTER TABLE shipment ADD COLUMN latitude DOUBLE PRECISION DEFAULT NULL;');
        $this->addSql('ALTER TABLE shipment ADD COLUMN longitude DOUBLE PRECISION DEFAULT NULL;');
        $this->addSql('ALTER TABLE shipment ADD COLUMN notes TEXT DEFAULT NULL;');

        // Customer: add contact info and active flag
        $this->addSql('ALTER TABLE customer ADD COLUMN address VARCHAR(255) DEFAULT NULL;');
        $this->addSql('ALTER TABLE customer ADD COLUMN contact_email VARCHAR(180) DEFAULT NULL;');
        $this->addSql('ALTER TABLE customer ADD COLUMN contact_phone VARCHAR(30) DEFAULT NULL;');
        $this->addSql('ALTER TABLE customer ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT TRUE;');

        // Route: add customer_id for linking routes to warehouses
        $this->addSql('ALTER TABLE route_plan ADD COLUMN customer_id BIGINT DEFAULT NULL;');
        $this->addSql('ALTER TABLE route_plan ADD CONSTRAINT fk_route_customer FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE SET NULL;');
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
