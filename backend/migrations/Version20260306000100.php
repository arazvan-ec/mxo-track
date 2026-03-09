<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Route optimization: vehicle capacity, shipment weight/volume/parcels,
 * parcel entity, customer frequency, route capacity tracking.
 */
final class Version20260306000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add vehicle capacity, shipment weight/volume/service_type, parcel entity, customer frequency, route capacity fields';
    }

    public function up(Schema $schema): void
    {
        // Vehicle capacity
        $this->addSql('ALTER TABLE vehicle ADD max_weight_kg NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE vehicle ADD max_volume_m3 NUMERIC(10, 4) DEFAULT NULL');
        $this->addSql('ALTER TABLE vehicle ADD max_parcels INT DEFAULT NULL');

        // Shipment weight/volume/service type
        $this->addSql("ALTER TABLE shipment ADD service_type VARCHAR(30) NOT NULL DEFAULT 'DELIVERY'");
        $this->addSql('ALTER TABLE shipment ADD total_weight_kg NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE shipment ADD total_volume_m3 NUMERIC(10, 4) DEFAULT NULL');
        $this->addSql('ALTER TABLE shipment ADD total_parcels INT NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE shipment ADD estimated_delivery_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE shipment ADD preferred_window_start TIME WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE shipment ADD preferred_window_end TIME WITHOUT TIME ZONE DEFAULT NULL');

        // Route capacity tracking
        $this->addSql('ALTER TABLE route_plan ADD total_weight_kg NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE route_plan ADD total_volume_m3 NUMERIC(10, 4) DEFAULT NULL');
        $this->addSql('ALTER TABLE route_plan ADD total_parcels INT DEFAULT NULL');
        $this->addSql('ALTER TABLE route_plan ADD total_distance_km NUMERIC(8, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE route_plan ADD estimated_duration_minutes INT DEFAULT NULL');

        // Customer frequency
        $this->addSql('ALTER TABLE customer ADD frequency VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE customer ADD preferred_delivery_slot VARCHAR(100) DEFAULT NULL');

        // Parcel entity (bultos)
        $this->addSql('CREATE TABLE parcel (
            id BIGSERIAL NOT NULL,
            public_id UUID NOT NULL,
            shipment_id BIGINT NOT NULL,
            sequence_number INT NOT NULL,
            weight_kg NUMERIC(10, 2) NOT NULL,
            volume_m3 NUMERIC(10, 4) NOT NULL,
            ean VARCHAR(30) DEFAULT NULL,
            description VARCHAR(255) DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'REGISTERED\',
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_parcel_public_id ON parcel (public_id)');
        $this->addSql('CREATE INDEX idx_parcel_shipment ON parcel (shipment_id)');
        $this->addSql('CREATE INDEX idx_parcel_ean ON parcel (ean)');
        $this->addSql('ALTER TABLE parcel ADD CONSTRAINT fk_parcel_shipment FOREIGN KEY (shipment_id) REFERENCES shipment (id) ON DELETE CASCADE');

        // Comment immutable timestamps
        $this->addSql("COMMENT ON COLUMN shipment.estimated_delivery_date IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN shipment.preferred_window_start IS '(DC2Type:time_immutable)'");
        $this->addSql("COMMENT ON COLUMN shipment.preferred_window_end IS '(DC2Type:time_immutable)'");
        $this->addSql("COMMENT ON COLUMN parcel.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN parcel.updated_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE parcel');

        $this->addSql('ALTER TABLE vehicle DROP max_weight_kg');
        $this->addSql('ALTER TABLE vehicle DROP max_volume_m3');
        $this->addSql('ALTER TABLE vehicle DROP max_parcels');

        $this->addSql('ALTER TABLE shipment DROP service_type');
        $this->addSql('ALTER TABLE shipment DROP total_weight_kg');
        $this->addSql('ALTER TABLE shipment DROP total_volume_m3');
        $this->addSql('ALTER TABLE shipment DROP total_parcels');
        $this->addSql('ALTER TABLE shipment DROP estimated_delivery_date');
        $this->addSql('ALTER TABLE shipment DROP preferred_window_start');
        $this->addSql('ALTER TABLE shipment DROP preferred_window_end');

        $this->addSql('ALTER TABLE route_plan DROP total_weight_kg');
        $this->addSql('ALTER TABLE route_plan DROP total_volume_m3');
        $this->addSql('ALTER TABLE route_plan DROP total_parcels');
        $this->addSql('ALTER TABLE route_plan DROP total_distance_km');
        $this->addSql('ALTER TABLE route_plan DROP estimated_duration_minutes');

        $this->addSql('ALTER TABLE customer DROP frequency');
        $this->addSql('ALTER TABLE customer DROP preferred_delivery_slot');
    }
}
