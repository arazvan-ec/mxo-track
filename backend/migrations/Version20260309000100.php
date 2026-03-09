<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * F1.4: Vehicle inspection checklist table.
 */
final class Version20260309000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create vehicle_inspection table for pre-route inspection checklists';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE vehicle_inspection (
                id BIGSERIAL PRIMARY KEY,
                public_id VARCHAR(26) NOT NULL,
                route_id BIGINT NOT NULL,
                driver_id BIGINT NOT NULL,
                items JSON NOT NULL,
                completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT fk_vehicle_inspection_route FOREIGN KEY (route_id) REFERENCES route_plan(id) ON DELETE CASCADE,
                CONSTRAINT fk_vehicle_inspection_driver FOREIGN KEY (driver_id) REFERENCES "user"(id) ON DELETE CASCADE,
                CONSTRAINT uniq_vehicle_inspection_public_id UNIQUE (public_id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS vehicle_inspection');
    }
}
