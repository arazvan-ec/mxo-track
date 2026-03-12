<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add (DC2Type:ulid) comment to all public_id columns that lack it.
 *
 * Without this comment, doctrine:schema:update tries to ALTER the UUID columns
 * to match the 'ulid' Doctrine type, causing a PostgreSQL cast error:
 * "column public_id cannot be cast automatically to type uuid"
 */
final class Version20260312000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add DC2Type:ulid comment to all public_id columns to prevent schema:update cast errors';
    }

    public function up(Schema $schema): void
    {
        // Tables from Version20260216000100 (initial schema)
        $this->addSql("COMMENT ON COLUMN customer.public_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN user_account.public_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN vehicle.public_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN vehicle_last_position.public_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN vehicle_positions.public_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN vehicle_checkpoint.public_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN shipment.public_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN shipment_event.public_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN csv_import_run.public_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN route_plan.public_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN route_stop.public_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN pod.public_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN audit_log.public_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN driver_action.public_id IS '(DC2Type:ulid)'");

        // Table from Version20260220010000
        $this->addSql("COMMENT ON COLUMN customer_location.public_id IS '(DC2Type:ulid)'");

        // Table from Version20260306000100
        $this->addSql("COMMENT ON COLUMN parcel.public_id IS '(DC2Type:ulid)'");

        // Table from Version20260309000100
        $this->addSql("COMMENT ON COLUMN vehicle_inspection.public_id IS '(DC2Type:ulid)'");

        // Table from Version20260309000200
        $this->addSql("COMMENT ON COLUMN driver_availability.public_id IS '(DC2Type:ulid)'");

        // Tables from Version20260312000100
        $this->addSql("COMMENT ON COLUMN notification_log.public_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN recipient_action.public_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN notification_preference.public_id IS '(DC2Type:ulid)'");

        // Already have comments but re-apply for safety (idempotent):
        // route_plan_template, push_subscription, webhook_endpoint, api_key
    }

    public function down(Schema $schema): void
    {
        $tables = [
            'customer', 'user_account', 'vehicle', 'vehicle_last_position',
            'vehicle_positions', 'vehicle_checkpoint', 'shipment', 'shipment_event',
            'csv_import_run', 'route_plan', 'route_stop', 'pod', 'audit_log',
            'driver_action', 'customer_location', 'parcel', 'vehicle_inspection',
            'driver_availability', 'notification_log', 'recipient_action',
            'notification_preference',
        ];

        foreach ($tables as $table) {
            $this->addSql("COMMENT ON COLUMN {$table}.public_id IS NULL");
        }
    }
}
