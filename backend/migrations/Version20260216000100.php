<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260216000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schema inicial transporte-tracking (BIGINT interno + ULID público)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS customer (id BIGSERIAL NOT NULL, public_id UUID NOT NULL, name VARCHAR(150) NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_customer_public_id ON customer (public_id);');

        $this->addSql('CREATE TABLE IF NOT EXISTS user_account (id BIGSERIAL NOT NULL, public_id UUID NOT NULL, customer_id BIGINT DEFAULT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password_hash VARCHAR(255) NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_USER_EMAIL ON user_account (email);');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_USER_PUBLIC_ID ON user_account (public_id);');

        $this->addSql('CREATE TABLE IF NOT EXISTS vehicle (id BIGSERIAL NOT NULL, public_id UUID NOT NULL, name VARCHAR(120) NOT NULL, traccar_device_id INT DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_TRACCAR_DEVICE ON vehicle (traccar_device_id);');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_VEHICLE_PUBLIC_ID ON vehicle (public_id);');

        $this->addSql('CREATE TABLE IF NOT EXISTS customer_vehicle (id BIGSERIAL NOT NULL, public_id UUID NOT NULL, customer_id BIGINT NOT NULL, vehicle_id BIGINT NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_customer_vehicle ON customer_vehicle (customer_id, vehicle_id);');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_customer_vehicle_public_id ON customer_vehicle (public_id);');

        $this->addSql('CREATE TABLE IF NOT EXISTS vehicle_last_position (id BIGSERIAL NOT NULL, public_id UUID NOT NULL, vehicle_id BIGINT NOT NULL, lat DOUBLE PRECISION NOT NULL, lng DOUBLE PRECISION NOT NULL, speed DOUBLE PRECISION NOT NULL, course DOUBLE PRECISION NOT NULL, accuracy DOUBLE PRECISION NOT NULL, device_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, server_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_vehicle_last_position_public_id ON vehicle_last_position (public_id);');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_vehicle_last_position_vehicle ON vehicle_last_position (vehicle_id);');

        $this->addSql('CREATE TABLE IF NOT EXISTS vehicle_positions (id BIGSERIAL NOT NULL, public_id UUID NOT NULL, vehicle_id BIGINT NOT NULL, lat DOUBLE PRECISION NOT NULL, lng DOUBLE PRECISION NOT NULL, speed DOUBLE PRECISION NOT NULL, course DOUBLE PRECISION NOT NULL, accuracy DOUBLE PRECISION NOT NULL, device_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, server_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, traccar_position_id INT DEFAULT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_vehicle_pos_time ON vehicle_positions (vehicle_id, device_time);');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_vehicle_position_public_id ON vehicle_positions (public_id);');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_vehicle_pos_time_desc ON vehicle_positions (vehicle_id, device_time DESC);');

        $this->addSql('CREATE TABLE IF NOT EXISTS vehicle_checkpoint (id BIGSERIAL NOT NULL, public_id UUID NOT NULL, vehicle_id BIGINT NOT NULL, last_device_time TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_traccar_position_id INT DEFAULT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_vehicle_checkpoint_public_id ON vehicle_checkpoint (public_id);');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_vehicle_checkpoint_vehicle ON vehicle_checkpoint (vehicle_id);');

        $this->addSql('CREATE TABLE IF NOT EXISTS shipment (id BIGSERIAL NOT NULL, public_id UUID NOT NULL, customer_id BIGINT NOT NULL, reference VARCHAR(80) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_SHIPMENT_REFERENCE ON shipment (reference);');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_SHIPMENT_PUBLIC_ID ON shipment (public_id);');

        $this->addSql('CREATE TABLE IF NOT EXISTS shipment_event (id BIGSERIAL NOT NULL, public_id UUID NOT NULL, shipment_id BIGINT NOT NULL, event_type VARCHAR(40) NOT NULL, payload JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_shipment_event_public_id ON shipment_event (public_id);');

        $this->addSql('CREATE TABLE IF NOT EXISTS csv_import_run (id BIGSERIAL NOT NULL, public_id UUID NOT NULL, customer_id BIGINT NOT NULL, created_count INT NOT NULL, skipped_count INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_csv_import_run_public_id ON csv_import_run (public_id);');

        $this->addSql('CREATE TABLE IF NOT EXISTS route_plan (id BIGSERIAL NOT NULL, public_id UUID NOT NULL, driver_id BIGINT DEFAULT NULL, vehicle_id BIGINT DEFAULT NULL, name VARCHAR(140) NOT NULL, status VARCHAR(20) NOT NULL, start_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, end_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_ROUTE_PUBLIC_ID ON route_plan (public_id);');

        $this->addSql('CREATE TABLE IF NOT EXISTS route_stop (id BIGSERIAL NOT NULL, public_id UUID NOT NULL, route_id BIGINT NOT NULL, sequence INT NOT NULL, address VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, exception_code VARCHAR(30) DEFAULT NULL, exception_notes TEXT DEFAULT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_ROUTE_STOP_SEQUENCE ON route_stop (route_id, sequence);');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_ROUTE_STOP_PUBLIC_ID ON route_stop (public_id);');

        $this->addSql('CREATE TABLE IF NOT EXISTS pod (id BIGSERIAL NOT NULL, public_id UUID NOT NULL, route_stop_id BIGINT NOT NULL, shipment_id BIGINT DEFAULT NULL, created_by_user_id BIGINT NOT NULL, signed_by_name VARCHAR(120) NOT NULL, recipient_id_encoded TEXT NOT NULL, confirmed_by_driver BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_POD_STOP ON pod (route_stop_id);');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_POD_PUBLIC_ID ON pod (public_id);');

        $this->addSql('CREATE TABLE IF NOT EXISTS audit_log (id BIGSERIAL NOT NULL, public_id UUID NOT NULL, actor_user_id BIGINT NOT NULL, action VARCHAR(80) NOT NULL, entity_type VARCHAR(80) NOT NULL, entity_id VARCHAR(40) NOT NULL, payload JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_audit_log_public_id ON audit_log (public_id);');

        $this->addSql('CREATE TABLE IF NOT EXISTS driver_action (id BIGSERIAL NOT NULL, public_id UUID NOT NULL, driver_user_id BIGINT NOT NULL, stop_id BIGINT NOT NULL, client_action_id UUID NOT NULL, type VARCHAR(30) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_driver_action ON driver_action (driver_user_id, client_action_id);');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_driver_action_public_id ON driver_action (public_id);');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE driver_action, audit_log, pod, route_stop, route_plan, csv_import_run, shipment_event, shipment, vehicle_checkpoint, vehicle_positions, vehicle_last_position, customer_vehicle, vehicle, user_account, customer;');
    }
}
