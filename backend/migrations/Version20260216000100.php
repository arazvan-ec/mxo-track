<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260216000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schema inicial transporte-tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE customer (id UUID NOT NULL, name VARCHAR(150) NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE TABLE user_account (id BIGSERIAL NOT NULL, public_id UUID NOT NULL, customer_id UUID DEFAULT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password_hash VARCHAR(255) NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_EMAIL ON user_account (email);');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_PUBLIC_ID ON user_account (public_id);');
        $this->addSql('CREATE TABLE vehicle (id UUID NOT NULL, public_id UUID NOT NULL, name VARCHAR(120) NOT NULL, traccar_device_id INT DEFAULT NULL, is_active BOOLEAN NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE TABLE customer_vehicle (customer_id UUID NOT NULL, vehicle_id UUID NOT NULL, PRIMARY KEY(customer_id, vehicle_id));');
        $this->addSql('CREATE UNIQUE INDEX uniq_customer_vehicle ON customer_vehicle (customer_id, vehicle_id));');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_TRACCAR_DEVICE ON vehicle (traccar_device_id);');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_VEHICLE_PUBLIC_ID ON vehicle (public_id);');
        $this->addSql('CREATE TABLE vehicle_last_position (vehicle_id UUID NOT NULL, lat DOUBLE PRECISION NOT NULL, lng DOUBLE PRECISION NOT NULL, speed DOUBLE PRECISION NOT NULL, course DOUBLE PRECISION NOT NULL, accuracy DOUBLE PRECISION NOT NULL, device_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, server_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(vehicle_id));');
        $this->addSql('CREATE TABLE vehicle_positions (id UUID NOT NULL, vehicle_id UUID NOT NULL, lat DOUBLE PRECISION NOT NULL, lng DOUBLE PRECISION NOT NULL, speed DOUBLE PRECISION NOT NULL, course DOUBLE PRECISION NOT NULL, accuracy DOUBLE PRECISION NOT NULL, device_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, server_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, traccar_position_id INT DEFAULT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE UNIQUE INDEX uniq_vehicle_pos_time ON vehicle_positions (vehicle_id, device_time);');
        $this->addSql('CREATE INDEX idx_vehicle_pos_time_desc ON vehicle_positions (vehicle_id, device_time);');
        $this->addSql('CREATE TABLE vehicle_checkpoint (vehicle_id UUID NOT NULL, last_device_time TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_traccar_position_id INT DEFAULT NULL, PRIMARY KEY(vehicle_id));');
        $this->addSql('CREATE TABLE shipment (id UUID NOT NULL, public_id UUID NOT NULL, customer_id UUID NOT NULL, reference VARCHAR(80) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_SHIPMENT_REFERENCE ON shipment (reference);');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_SHIPMENT_PUBLIC_ID ON shipment (public_id);');
        $this->addSql('CREATE TABLE shipment_event (id UUID NOT NULL, shipment_id UUID NOT NULL, event_type VARCHAR(40) NOT NULL, payload JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE TABLE csv_import_run (id UUID NOT NULL, customer_id UUID NOT NULL, created_count INT NOT NULL, skipped_count INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE TABLE route_plan (id UUID NOT NULL, public_id UUID NOT NULL, driver_id UUID DEFAULT NULL, vehicle_id UUID DEFAULT NULL, name VARCHAR(140) NOT NULL, status VARCHAR(20) NOT NULL, start_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, end_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ROUTE_PUBLIC_ID ON route_plan (public_id);');
        $this->addSql('CREATE TABLE route_stop (id UUID NOT NULL, public_id UUID NOT NULL, route_id UUID NOT NULL, sequence INT NOT NULL, address VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, exception_code VARCHAR(30) DEFAULT NULL, exception_notes TEXT DEFAULT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE INDEX IDX_ROUTE_STOP_SEQUENCE ON route_stop (route_id, sequence);');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ROUTE_STOP_PUBLIC_ID ON route_stop (public_id);');
        $this->addSql('CREATE TABLE pod (id UUID NOT NULL, public_id UUID NOT NULL, route_stop_id UUID NOT NULL, shipment_id UUID DEFAULT NULL, created_by_user_id UUID NOT NULL, signed_by_name VARCHAR(120) NOT NULL, recipient_id_encoded TEXT NOT NULL, confirmed_by_driver BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_POD_STOP ON pod (route_stop_id);');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_POD_PUBLIC_ID ON pod (public_id);');
        $this->addSql('CREATE TABLE audit_log (id UUID NOT NULL, actor_user_id UUID NOT NULL, action VARCHAR(80) NOT NULL, entity_type VARCHAR(80) NOT NULL, entity_id UUID NOT NULL, payload JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE TABLE driver_action (id UUID NOT NULL, driver_user_id UUID NOT NULL, stop_id UUID NOT NULL, client_action_id UUID NOT NULL, type VARCHAR(30) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE UNIQUE INDEX uniq_driver_action ON driver_action (driver_user_id, client_action_id);');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE driver_action, audit_log, pod, route_stop, route_plan, csv_import_run, shipment_event, shipment, vehicle_checkpoint, vehicle_positions, vehicle_last_position, customer_vehicle, vehicle, user_account, customer;');
    }
}
