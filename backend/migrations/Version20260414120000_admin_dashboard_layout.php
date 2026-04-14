<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adjust admin_dashboard page layout for registry-driven rendering:
 * - Reorder widgets to match visual order (dashboard_kpis first)
 * - Remove activity_feed from layout (not used in admin dashboard page mode)
 * - Ensure 5 widgets: dashboard_kpis(0), system_health(1), infrastructure_metrics(2),
 *   mini_reports(3), reports_banner(4) — all at sheet_state='full'.
 */
final class Version20260414120000_admin_dashboard_layout extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reorder admin_dashboard layout widgets for registry-driven page rendering';
    }

    public function up(Schema $schema): void
    {
        // Remove activity_feed from admin_dashboard layout
        $this->addSql("
            DELETE FROM page_layout_widget
            WHERE page_layout_id = (
                SELECT id FROM page_layout
                WHERE page_key = 'admin_dashboard' AND customer_id IS NULL
                LIMIT 1
            )
            AND widget_definition_id = (
                SELECT id FROM widget_definition WHERE type = 'activity_feed'
            )
        ");

        // Update positions to desired visual order
        // dashboard_kpis → 0, system_health → 1, infrastructure_metrics → 2,
        // mini_reports → 3, reports_banner → 4
        $widgetOrder = [
            'dashboard_kpis' => 0,
            'system_health' => 1,
            'infrastructure_metrics' => 2,
            'mini_reports' => 3,
            'reports_banner' => 4,
        ];

        foreach ($widgetOrder as $widgetType => $position) {
            $this->addSql("
                UPDATE page_layout_widget
                SET position = {$position}, sheet_state = 'full'
                WHERE page_layout_id = (
                    SELECT id FROM page_layout
                    WHERE page_key = 'admin_dashboard' AND customer_id IS NULL
                    LIMIT 1
                )
                AND widget_definition_id = (
                    SELECT id FROM widget_definition WHERE type = '{$widgetType}'
                )
            ");
        }
    }

    public function down(Schema $schema): void
    {
        // Restore activity_feed to admin_dashboard layout
        $this->addSql("
            INSERT INTO page_layout_widget (public_id, page_layout_id, widget_definition_id, sheet_state, position, created_at)
            SELECT gen_random_uuid(), pl.id, wd.id, 'full', 4, NOW()
            FROM page_layout pl, widget_definition wd
            WHERE pl.page_key = 'admin_dashboard' AND pl.customer_id IS NULL AND wd.type = 'activity_feed'
        ");

        // Restore original positions
        $this->addSql("
            UPDATE page_layout_widget SET position = 0
            WHERE page_layout_id = (SELECT id FROM page_layout WHERE page_key = 'admin_dashboard' AND customer_id IS NULL LIMIT 1)
            AND widget_definition_id = (SELECT id FROM widget_definition WHERE type = 'system_health')
        ");
        $this->addSql("
            UPDATE page_layout_widget SET position = 1
            WHERE page_layout_id = (SELECT id FROM page_layout WHERE page_key = 'admin_dashboard' AND customer_id IS NULL LIMIT 1)
            AND widget_definition_id = (SELECT id FROM widget_definition WHERE type = 'infrastructure_metrics')
        ");
        $this->addSql("
            UPDATE page_layout_widget SET position = 2
            WHERE page_layout_id = (SELECT id FROM page_layout WHERE page_key = 'admin_dashboard' AND customer_id IS NULL LIMIT 1)
            AND widget_definition_id = (SELECT id FROM widget_definition WHERE type = 'dashboard_kpis')
        ");
        $this->addSql("
            UPDATE page_layout_widget SET position = 3
            WHERE page_layout_id = (SELECT id FROM page_layout WHERE page_key = 'admin_dashboard' AND customer_id IS NULL LIMIT 1)
            AND widget_definition_id = (SELECT id FROM widget_definition WHERE type = 'mini_reports')
        ");
        $this->addSql("
            UPDATE page_layout_widget SET position = 5
            WHERE page_layout_id = (SELECT id FROM page_layout WHERE page_key = 'admin_dashboard' AND customer_id IS NULL LIMIT 1)
            AND widget_definition_id = (SELECT id FROM widget_definition WHERE type = 'reports_banner')
        ");
    }
}
