<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add customer_location table, Route.origin_location_id, RouteStop.is_origin';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE customer_location (
            id BIGSERIAL NOT NULL,
            public_id UUID NOT NULL,
            customer_id BIGINT NOT NULL,
            name VARCHAR(150) NOT NULL,
            address VARCHAR(255) NOT NULL,
            latitude DOUBLE PRECISION DEFAULT NULL,
            longitude DOUBLE PRECISION DEFAULT NULL,
            is_default BOOLEAN NOT NULL DEFAULT FALSE,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            PRIMARY KEY (id),
            CONSTRAINT uniq_customer_location_public_id UNIQUE (public_id),
            CONSTRAINT fk_customer_location_customer FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE CASCADE
        )');

        $this->addSql('ALTER TABLE route_plan ADD COLUMN origin_location_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE route_plan ADD CONSTRAINT fk_route_origin_location FOREIGN KEY (origin_location_id) REFERENCES customer_location (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE route_stop ADD COLUMN is_origin BOOLEAN NOT NULL DEFAULT FALSE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE route_stop DROP COLUMN is_origin');

        $this->addSql('ALTER TABLE route_plan DROP CONSTRAINT IF EXISTS fk_route_origin_location');
        $this->addSql('ALTER TABLE route_plan DROP COLUMN origin_location_id');

        $this->addSql('DROP TABLE customer_location');
    }
}
