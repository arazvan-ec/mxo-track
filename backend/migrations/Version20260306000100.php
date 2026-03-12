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
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'vehicle\' AND column_name = \'max_weight_kg\') THEN ALTER TABLE vehicle ADD max_weight_kg NUMERIC(10, 2) DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'vehicle\' AND column_name = \'max_volume_m3\') THEN ALTER TABLE vehicle ADD max_volume_m3 NUMERIC(10, 4) DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'vehicle\' AND column_name = \'max_parcels\') THEN ALTER TABLE vehicle ADD max_parcels INT DEFAULT NULL; END IF; END $$');

        // Shipment weight/volume/service type
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'shipment\' AND column_name = \'service_type\') THEN ALTER TABLE shipment ADD service_type VARCHAR(30) NOT NULL DEFAULT \'DELIVERY\'; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'shipment\' AND column_name = \'total_weight_kg\') THEN ALTER TABLE shipment ADD total_weight_kg NUMERIC(10, 2) DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'shipment\' AND column_name = \'total_volume_m3\') THEN ALTER TABLE shipment ADD total_volume_m3 NUMERIC(10, 4) DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'shipment\' AND column_name = \'total_parcels\') THEN ALTER TABLE shipment ADD total_parcels INT NOT NULL DEFAULT 1; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'shipment\' AND column_name = \'estimated_delivery_date\') THEN ALTER TABLE shipment ADD estimated_delivery_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'shipment\' AND column_name = \'preferred_window_start\') THEN ALTER TABLE shipment ADD preferred_window_start TIME WITHOUT TIME ZONE DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'shipment\' AND column_name = \'preferred_window_end\') THEN ALTER TABLE shipment ADD preferred_window_end TIME WITHOUT TIME ZONE DEFAULT NULL; END IF; END $$');

        // Route capacity tracking
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'route_plan\' AND column_name = \'total_weight_kg\') THEN ALTER TABLE route_plan ADD total_weight_kg NUMERIC(10, 2) DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'route_plan\' AND column_name = \'total_volume_m3\') THEN ALTER TABLE route_plan ADD total_volume_m3 NUMERIC(10, 4) DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'route_plan\' AND column_name = \'total_parcels\') THEN ALTER TABLE route_plan ADD total_parcels INT DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'route_plan\' AND column_name = \'total_distance_km\') THEN ALTER TABLE route_plan ADD total_distance_km NUMERIC(8, 2) DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'route_plan\' AND column_name = \'estimated_duration_minutes\') THEN ALTER TABLE route_plan ADD estimated_duration_minutes INT DEFAULT NULL; END IF; END $$');

        // Customer frequency
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'customer\' AND column_name = \'frequency\') THEN ALTER TABLE customer ADD frequency VARCHAR(20) DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'customer\' AND column_name = \'preferred_delivery_slot\') THEN ALTER TABLE customer ADD preferred_delivery_slot VARCHAR(100) DEFAULT NULL; END IF; END $$');

        // Parcel entity (bultos)
        $this->addSql('CREATE TABLE IF NOT EXISTS parcel (
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
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_parcel_public_id ON parcel (public_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_parcel_shipment ON parcel (shipment_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_parcel_ean ON parcel (ean)');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = \'fk_parcel_shipment\') THEN ALTER TABLE parcel ADD CONSTRAINT fk_parcel_shipment FOREIGN KEY (shipment_id) REFERENCES shipment (id) ON DELETE CASCADE; END IF; END $$');

        // Comment immutable timestamps
        $this->addSql("COMMENT ON COLUMN shipment.estimated_delivery_date IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN shipment.preferred_window_start IS '(DC2Type:time_immutable)'");
        $this->addSql("COMMENT ON COLUMN shipment.preferred_window_end IS '(DC2Type:time_immutable)'");
        $this->addSql("COMMENT ON COLUMN parcel.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN parcel.updated_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS parcel');

        $this->addSql('ALTER TABLE vehicle DROP COLUMN IF EXISTS max_weight_kg');
        $this->addSql('ALTER TABLE vehicle DROP COLUMN IF EXISTS max_volume_m3');
        $this->addSql('ALTER TABLE vehicle DROP COLUMN IF EXISTS max_parcels');

        $this->addSql('ALTER TABLE shipment DROP COLUMN IF EXISTS service_type');
        $this->addSql('ALTER TABLE shipment DROP COLUMN IF EXISTS total_weight_kg');
        $this->addSql('ALTER TABLE shipment DROP COLUMN IF EXISTS total_volume_m3');
        $this->addSql('ALTER TABLE shipment DROP COLUMN IF EXISTS total_parcels');
        $this->addSql('ALTER TABLE shipment DROP COLUMN IF EXISTS estimated_delivery_date');
        $this->addSql('ALTER TABLE shipment DROP COLUMN IF EXISTS preferred_window_start');
        $this->addSql('ALTER TABLE shipment DROP COLUMN IF EXISTS preferred_window_end');

        $this->addSql('ALTER TABLE route_plan DROP COLUMN IF EXISTS total_weight_kg');
        $this->addSql('ALTER TABLE route_plan DROP COLUMN IF EXISTS total_volume_m3');
        $this->addSql('ALTER TABLE route_plan DROP COLUMN IF EXISTS total_parcels');
        $this->addSql('ALTER TABLE route_plan DROP COLUMN IF EXISTS total_distance_km');
        $this->addSql('ALTER TABLE route_plan DROP COLUMN IF EXISTS estimated_duration_minutes');

        $this->addSql('ALTER TABLE customer DROP COLUMN IF EXISTS frequency');
        $this->addSql('ALTER TABLE customer DROP COLUMN IF EXISTS preferred_delivery_slot');
    }
}
