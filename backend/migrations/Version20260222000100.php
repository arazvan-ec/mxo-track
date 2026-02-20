<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add route_id FK to vehicle_positions for route-position association';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vehicle_positions ADD route_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE vehicle_positions ADD CONSTRAINT fk_vehicle_positions_route FOREIGN KEY (route_id) REFERENCES route_plan (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_vehicle_positions_route_time ON vehicle_positions (route_id, device_time)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_vehicle_positions_route_time');
        $this->addSql('ALTER TABLE vehicle_positions DROP CONSTRAINT fk_vehicle_positions_route');
        $this->addSql('ALTER TABLE vehicle_positions DROP COLUMN route_id');
    }
}
