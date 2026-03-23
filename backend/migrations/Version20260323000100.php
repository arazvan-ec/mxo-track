<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create widget_definition, page_layout, and page_layout_widget tables
 * with seed data for all 10 widget types and 8 default page layouts.
 */
final class Version20260323000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add widget system tables (widget_definition, page_layout, page_layout_widget) with seed data';
    }

    public function up(Schema $schema): void
    {
        // -- widget_definition table
        $this->addSql('CREATE TABLE widget_definition (
            id BIGSERIAL PRIMARY KEY,
            public_id UUID NOT NULL,
            type VARCHAR(50) NOT NULL,
            label VARCHAR(120) NOT NULL,
            description TEXT DEFAULT NULL,
            preview_image VARCHAR(255) DEFAULT NULL,
            active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_widget_definition_public_id ON widget_definition (public_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_widget_definition_type ON widget_definition (type)');
        $this->addSql('COMMENT ON COLUMN widget_definition.public_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN widget_definition.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN widget_definition.updated_at IS \'(DC2Type:datetime_immutable)\'');

        // -- page_layout table
        $this->addSql('CREATE TABLE page_layout (
            id BIGSERIAL PRIMARY KEY,
            public_id UUID NOT NULL,
            page_key VARCHAR(50) NOT NULL,
            customer_id BIGINT DEFAULT NULL,
            active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_page_layout_public_id ON page_layout (public_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_page_layout_page_customer ON page_layout (page_key, customer_id)');
        $this->addSql('ALTER TABLE page_layout ADD CONSTRAINT fk_page_layout_customer FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_page_layout_customer ON page_layout (customer_id)');
        $this->addSql('COMMENT ON COLUMN page_layout.public_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN page_layout.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN page_layout.updated_at IS \'(DC2Type:datetime_immutable)\'');

        // -- page_layout_widget table
        $this->addSql('CREATE TABLE page_layout_widget (
            id BIGSERIAL PRIMARY KEY,
            public_id UUID NOT NULL,
            page_layout_id BIGINT NOT NULL,
            widget_definition_id BIGINT NOT NULL,
            sheet_state VARCHAR(20) NOT NULL,
            position SMALLINT NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_page_layout_widget_public_id ON page_layout_widget (public_id)');
        $this->addSql('CREATE INDEX idx_plw_layout_state_position ON page_layout_widget (page_layout_id, sheet_state, position)');
        $this->addSql('ALTER TABLE page_layout_widget ADD CONSTRAINT fk_plw_page_layout FOREIGN KEY (page_layout_id) REFERENCES page_layout (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE page_layout_widget ADD CONSTRAINT fk_plw_widget_definition FOREIGN KEY (widget_definition_id) REFERENCES widget_definition (id) ON DELETE CASCADE');
        $this->addSql('COMMENT ON COLUMN page_layout_widget.public_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN page_layout_widget.created_at IS \'(DC2Type:datetime_immutable)\'');

        // -- Seed widget definitions
        $this->addSql("INSERT INTO widget_definition (public_id, type, label, description, active, created_at, updated_at) VALUES
            (gen_random_uuid(), 'metric_pairs', 'Metric Pairs', 'Key metrics in paired format (scope, distance, time)', TRUE, NOW(), NOW()),
            (gen_random_uuid(), 'route_card_list', 'Route Card List', 'Scrollable list of route cards with stops and metrics', TRUE, NOW(), NOW()),
            (gen_random_uuid(), 'stop_list', 'Stop List', 'Ordered list of stops with status indicators', TRUE, NOW(), NOW()),
            (gen_random_uuid(), 'vehicle_info', 'Vehicle Info', 'Vehicle details panel (plate, model, capacity)', TRUE, NOW(), NOW()),
            (gen_random_uuid(), 'driver_info', 'Driver Info', 'Driver details panel (name, phone, status)', TRUE, NOW(), NOW()),
            (gen_random_uuid(), 'shipment_details', 'Shipment Details', 'Shipment info (recipient, address, status)', TRUE, NOW(), NOW()),
            (gen_random_uuid(), 'delivery_timeline', 'Delivery Timeline', 'Vertical timeline of delivery events', TRUE, NOW(), NOW()),
            (gen_random_uuid(), 'kpi_pills', 'KPI Pills', 'Compact KPI pills (on-time %, delivered, pending)', TRUE, NOW(), NOW()),
            (gen_random_uuid(), 'map_legend', 'Map Legend', 'Map legend showing route colors and markers', TRUE, NOW(), NOW()),
            (gen_random_uuid(), 'route_comparison', 'Route Comparison', 'Before/after comparison of original vs optimized routes', TRUE, NOW(), NOW())
        ");

        // -- Seed global page layouts (customer_id = NULL)
        $pageKeys = [
            'test_routing', 'fleet_map', 'route_planner', 'route_analysis',
            'route_detail', 'shipment_tracking', 'driver_route', 'customer_tracking',
        ];
        foreach ($pageKeys as $pageKey) {
            $this->addSql("INSERT INTO page_layout (public_id, page_key, customer_id, active, created_at, updated_at) VALUES (gen_random_uuid(), '{$pageKey}', NULL, TRUE, NOW(), NOW())");
        }

        // -- Seed page layout widgets
        // Helper: widget placements per page per state
        // Format: [pageKey => [state => [widget_type, ...]]]
        $defaults = [
            'test_routing' => [
                'collapsed' => ['metric_pairs'],
                'half' => ['metric_pairs', 'route_card_list'],
                'full' => ['metric_pairs', 'route_card_list', 'route_comparison', 'map_legend'],
            ],
            'fleet_map' => [
                'collapsed' => ['kpi_pills'],
                'half' => ['kpi_pills', 'vehicle_info'],
                'full' => ['kpi_pills', 'vehicle_info', 'driver_info', 'map_legend'],
            ],
            'route_planner' => [
                'collapsed' => ['metric_pairs'],
                'half' => ['metric_pairs', 'route_card_list'],
                'full' => ['metric_pairs', 'route_card_list', 'stop_list', 'map_legend'],
            ],
            'route_analysis' => [
                'collapsed' => ['metric_pairs'],
                'half' => ['metric_pairs', 'route_comparison'],
                'full' => ['metric_pairs', 'route_comparison', 'route_card_list', 'kpi_pills'],
            ],
            'route_detail' => [
                'collapsed' => ['metric_pairs'],
                'half' => ['metric_pairs', 'stop_list'],
                'full' => ['metric_pairs', 'stop_list', 'delivery_timeline', 'vehicle_info'],
            ],
            'shipment_tracking' => [
                'collapsed' => ['shipment_details'],
                'half' => ['shipment_details', 'delivery_timeline'],
                'full' => ['shipment_details', 'delivery_timeline', 'map_legend'],
            ],
            'driver_route' => [
                'collapsed' => ['metric_pairs'],
                'half' => ['metric_pairs', 'stop_list'],
                'full' => ['metric_pairs', 'stop_list', 'delivery_timeline', 'driver_info'],
            ],
            'customer_tracking' => [
                'collapsed' => ['shipment_details'],
                'half' => ['shipment_details', 'delivery_timeline'],
                'full' => ['shipment_details', 'delivery_timeline', 'map_legend'],
            ],
        ];

        foreach ($defaults as $pageKey => $states) {
            foreach ($states as $state => $widgetTypes) {
                foreach ($widgetTypes as $position => $widgetType) {
                    $this->addSql("INSERT INTO page_layout_widget (public_id, page_layout_id, widget_definition_id, sheet_state, position, created_at)
                        SELECT gen_random_uuid(), pl.id, wd.id, '{$state}', {$position}, NOW()
                        FROM page_layout pl, widget_definition wd
                        WHERE pl.page_key = '{$pageKey}' AND pl.customer_id IS NULL AND wd.type = '{$widgetType}'");
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS page_layout_widget');
        $this->addSql('DROP TABLE IF EXISTS page_layout');
        $this->addSql('DROP TABLE IF EXISTS widget_definition');
    }
}
