<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Performance indexes for common query patterns.
 */
final class Version20260222200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add performance indexes for common query patterns';
    }

    public function up(Schema $schema): void
    {
        // RouteStop: counting pending/delivered stops per route
        $this->addSql('CREATE INDEX idx_route_stop_route_status ON route_stop (route_id, status)');

        // Shipment: customer shipment lists ordered by date
        $this->addSql('CREATE INDEX idx_shipment_customer_created ON shipment (customer_id, created_at DESC)');

        // ShipmentEvent: event timeline per shipment
        $this->addSql('CREATE INDEX idx_shipment_event_shipment_created ON shipment_event (shipment_id, created_at DESC)');

        // VehiclePosition: latest positions query — DESC index for efficient ORDER BY ... DESC LIMIT
        $this->addSql('CREATE INDEX idx_vehicle_positions_vehicle_time_desc ON vehicle_positions (vehicle_id, device_time DESC)');

        // AuditLog: date-range queries for admin audit review
        $this->addSql('CREATE INDEX idx_audit_log_created_at ON audit_log (created_at DESC)');

        // User: customer user lookups (used in customer admin, tenant filter)
        $this->addSql('CREATE INDEX idx_user_account_customer ON user_account (customer_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_route_stop_route_status');
        $this->addSql('DROP INDEX IF EXISTS idx_shipment_customer_created');
        $this->addSql('DROP INDEX IF EXISTS idx_shipment_event_shipment_created');
        $this->addSql('DROP INDEX IF EXISTS idx_vehicle_positions_vehicle_time_desc');
        $this->addSql('DROP INDEX IF EXISTS idx_audit_log_created_at');
        $this->addSql('DROP INDEX IF EXISTS idx_user_account_customer');
    }
}
