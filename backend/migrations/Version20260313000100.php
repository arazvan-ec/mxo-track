<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fix public_id columns: convert VARCHAR(26) to UUID.
 *
 * Tables vehicle_inspection, driver_availability, push_subscription were created
 * with public_id VARCHAR(26), but the Doctrine mapping expects type 'ulid' which
 * maps to PostgreSQL uuid. Since VARCHAR(26) cannot hold UUID strings (36 chars),
 * these tables have no persisted rows — safe to drop and recreate the column type.
 */
final class Version20260313000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert public_id columns from VARCHAR(26) to UUID in vehicle_inspection, driver_availability, push_subscription';
    }

    public function up(Schema $schema): void
    {
        $tables = ['vehicle_inspection', 'driver_availability', 'push_subscription'];

        foreach ($tables as $table) {
            // Drop any rows that might exist (shouldn't be any, VARCHAR(26) can't hold UUID)
            // then alter the column type
            $this->addSql(sprintf(
                'ALTER TABLE %s ALTER COLUMN public_id DROP DEFAULT',
                $table,
            ));
            $this->addSql(sprintf(
                'ALTER TABLE %s ALTER COLUMN public_id TYPE uuid USING CASE WHEN LENGTH(public_id) = 36 THEN public_id::uuid ELSE NULL END',
                $table,
            ));
        }
    }

    public function down(Schema $schema): void
    {
        $tables = ['vehicle_inspection', 'driver_availability', 'push_subscription'];

        foreach ($tables as $table) {
            $this->addSql(sprintf(
                'ALTER TABLE %s ALTER COLUMN public_id TYPE VARCHAR(26) USING public_id::text',
                $table,
            ));
        }
    }
}
