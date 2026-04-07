<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seed dashboard widget definitions and admin_dashboard page layout.
 */
final class Version20260407000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add 5 dashboard widget definitions and admin_dashboard page layout with all widgets';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            DO \$\$
            DECLARE
                v_wd_max    BIGINT;
                v_layout_id BIGINT;
                v_plw_max   BIGINT;
            BEGIN
                -- Get current max id for widget_definition
                SELECT COALESCE(MAX(id), 0) INTO v_wd_max FROM widget_definition;

                -- Insert 5 new widget definitions
                INSERT INTO widget_definition (id, public_id, type, label, description, preview_image, active, created_at, updated_at)
                VALUES
                    (v_wd_max + 1, gen_random_uuid(), 'system_health', 'System Health', '6 service status cards (DB, Redis, Traccar, Mercure, OSRM, VROOM)', NULL, true, NOW(), NOW()),
                    (v_wd_max + 2, gen_random_uuid(), 'infrastructure_metrics', 'Infrastructure Metrics', '3 metric cards (positions table, DB size, last ingestion)', NULL, true, NOW(), NOW()),
                    (v_wd_max + 3, gen_random_uuid(), 'dashboard_kpis', 'Dashboard KPIs', '4 KPI cards (routes, stops, imports, positions/hour)', NULL, true, NOW(), NOW()),
                    (v_wd_max + 4, gen_random_uuid(), 'mini_reports', 'Mini Reports', 'Chart (7-day deliveries) + top 5 drivers', NULL, true, NOW(), NOW()),
                    (v_wd_max + 5, gen_random_uuid(), 'activity_feed', 'Activity Feed', 'Live position feed via Mercure SSE', NULL, true, NOW(), NOW());

                -- Create admin_dashboard global page layout
                INSERT INTO page_layout (id, public_id, page_key, customer_id, active, created_at, updated_at)
                VALUES (
                    (SELECT COALESCE(MAX(id), 0) + 1 FROM page_layout),
                    gen_random_uuid(),
                    'admin_dashboard',
                    NULL,
                    true,
                    NOW(),
                    NOW()
                )
                RETURNING id INTO v_layout_id;

                -- Get current max id for page_layout_widget
                SELECT COALESCE(MAX(id), 0) INTO v_plw_max FROM page_layout_widget;

                -- Insert all 5 widgets at 'half' state (default view)
                INSERT INTO page_layout_widget (id, public_id, page_layout_id, widget_definition_id, sheet_state, position, created_at)
                VALUES
                    (v_plw_max + 1, gen_random_uuid(), v_layout_id, v_wd_max + 1, 'half', 0, NOW()),
                    (v_plw_max + 2, gen_random_uuid(), v_layout_id, v_wd_max + 2, 'half', 1, NOW()),
                    (v_plw_max + 3, gen_random_uuid(), v_layout_id, v_wd_max + 3, 'half', 2, NOW()),
                    (v_plw_max + 4, gen_random_uuid(), v_layout_id, v_wd_max + 4, 'half', 3, NOW()),
                    (v_plw_max + 5, gen_random_uuid(), v_layout_id, v_wd_max + 5, 'half', 4, NOW());
            END
            \$\$;
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("
            DELETE FROM page_layout_widget WHERE page_layout_id IN (
                SELECT id FROM page_layout WHERE page_key = 'admin_dashboard'
            );
            DELETE FROM page_layout WHERE page_key = 'admin_dashboard';
            DELETE FROM widget_definition WHERE type IN (
                'system_health', 'infrastructure_metrics', 'dashboard_kpis', 'mini_reports', 'activity_feed'
            );
        ");
    }
}
