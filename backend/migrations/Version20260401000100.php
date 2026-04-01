<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add route_card_list widget to fleet_map layout (half + full states).
 */
final class Version20260401000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add route_card_list widget to fleet_map page layout in half and full states';
    }

    public function up(Schema $schema): void
    {
        // Remove current half and full widgets for fleet_map global layout
        $this->addSql("
            DELETE FROM page_layout_widget
            WHERE page_layout_id = (
                SELECT id FROM page_layout WHERE page_key = 'fleet_map' AND customer_id IS NULL
            )
            AND sheet_state IN ('half', 'full')
        ");

        // Reset sequence to avoid PK collision (BIGSERIAL doesn't auto-adjust after DELETE)
        $this->addSql("SELECT setval('page_layout_widget_id_seq', (SELECT COALESCE(MAX(id), 0) FROM page_layout_widget))");

        // New half state: kpi_pills, route_card_list
        $halfWidgets = ['kpi_pills', 'route_card_list'];
        foreach ($halfWidgets as $position => $widgetType) {
            $this->addSql("
                INSERT INTO page_layout_widget (public_id, page_layout_id, widget_definition_id, sheet_state, position, created_at)
                SELECT gen_random_uuid(), pl.id, wd.id, 'half', {$position}, NOW()
                FROM page_layout pl, widget_definition wd
                WHERE pl.page_key = 'fleet_map' AND pl.customer_id IS NULL AND wd.type = '{$widgetType}'
            ");
        }

        // New full state: kpi_pills, route_card_list, vehicle_info, driver_info, map_legend
        $fullWidgets = ['kpi_pills', 'route_card_list', 'vehicle_info', 'driver_info', 'map_legend'];
        foreach ($fullWidgets as $position => $widgetType) {
            $this->addSql("
                INSERT INTO page_layout_widget (public_id, page_layout_id, widget_definition_id, sheet_state, position, created_at)
                SELECT gen_random_uuid(), pl.id, wd.id, 'full', {$position}, NOW()
                FROM page_layout pl, widget_definition wd
                WHERE pl.page_key = 'fleet_map' AND pl.customer_id IS NULL AND wd.type = '{$widgetType}'
            ");
        }
    }

    public function down(Schema $schema): void
    {
        // Restore original half and full widgets for fleet_map
        $this->addSql("
            DELETE FROM page_layout_widget
            WHERE page_layout_id = (
                SELECT id FROM page_layout WHERE page_key = 'fleet_map' AND customer_id IS NULL
            )
            AND sheet_state IN ('half', 'full')
        ");

        // Reset sequence to avoid PK collision
        $this->addSql("SELECT setval('page_layout_widget_id_seq', (SELECT COALESCE(MAX(id), 0) FROM page_layout_widget))");

        // Restore original half: kpi_pills, vehicle_info
        $halfWidgets = ['kpi_pills', 'vehicle_info'];
        foreach ($halfWidgets as $position => $widgetType) {
            $this->addSql("
                INSERT INTO page_layout_widget (public_id, page_layout_id, widget_definition_id, sheet_state, position, created_at)
                SELECT gen_random_uuid(), pl.id, wd.id, 'half', {$position}, NOW()
                FROM page_layout pl, widget_definition wd
                WHERE pl.page_key = 'fleet_map' AND pl.customer_id IS NULL AND wd.type = '{$widgetType}'
            ");
        }

        // Restore original full: kpi_pills, vehicle_info, driver_info, map_legend
        $fullWidgets = ['kpi_pills', 'vehicle_info', 'driver_info', 'map_legend'];
        foreach ($fullWidgets as $position => $widgetType) {
            $this->addSql("
                INSERT INTO page_layout_widget (public_id, page_layout_id, widget_definition_id, sheet_state, position, created_at)
                SELECT gen_random_uuid(), pl.id, wd.id, 'full', {$position}, NOW()
                FROM page_layout pl, widget_definition wd
                WHERE pl.page_key = 'fleet_map' AND pl.customer_id IS NULL AND wd.type = '{$widgetType}'
            ");
        }
    }
}
