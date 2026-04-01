<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add route_card_list widget to fleet_map layout (half + full states).
 *
 * Uses explicit id generation via (SELECT MAX(id) FROM page_layout_widget)
 * instead of relying on the BIGSERIAL sequence, which was desynchronised by
 * previous failed migration attempts.
 */
final class Version20260401000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add route_card_list widget to fleet_map page layout in half and full states';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            DO \$\$
            DECLARE
                v_layout_id BIGINT;
                v_max_id    BIGINT;
            BEGIN
                -- Resolve fleet_map global layout
                SELECT id INTO v_layout_id
                FROM page_layout
                WHERE page_key = 'fleet_map' AND customer_id IS NULL;

                IF v_layout_id IS NULL THEN
                    RAISE EXCEPTION 'fleet_map global layout not found';
                END IF;

                -- Remove current half and full widgets (idempotent)
                DELETE FROM page_layout_widget
                WHERE page_layout_id = v_layout_id
                  AND sheet_state IN ('half', 'full');

                -- Get current max id to generate explicit ids (avoids BIGSERIAL sequence issues)
                SELECT COALESCE(MAX(id), 0) INTO v_max_id FROM page_layout_widget;

                -- half: kpi_pills (pos 0), route_card_list (pos 1)
                INSERT INTO page_layout_widget (id, public_id, page_layout_id, widget_definition_id, sheet_state, position, created_at)
                SELECT v_max_id + row_number() OVER (), gen_random_uuid(), v_layout_id, wd.id, 'half', pos, NOW()
                FROM (VALUES ('kpi_pills', 0), ('route_card_list', 1)) AS t(wtype, pos)
                JOIN widget_definition wd ON wd.type = t.wtype;

                -- Update v_max_id after first batch
                SELECT COALESCE(MAX(id), 0) INTO v_max_id FROM page_layout_widget;

                -- full: kpi_pills (0), route_card_list (1), vehicle_info (2), driver_info (3), map_legend (4)
                INSERT INTO page_layout_widget (id, public_id, page_layout_id, widget_definition_id, sheet_state, position, created_at)
                SELECT v_max_id + row_number() OVER (), gen_random_uuid(), v_layout_id, wd.id, 'full', pos, NOW()
                FROM (VALUES ('kpi_pills', 0), ('route_card_list', 1), ('vehicle_info', 2), ('driver_info', 3), ('map_legend', 4)) AS t(wtype, pos)
                JOIN widget_definition wd ON wd.type = t.wtype;

                -- Fix the BIGSERIAL sequence so future inserts don't collide
                PERFORM setval(
                    pg_get_serial_sequence('page_layout_widget', 'id'),
                    (SELECT MAX(id) FROM page_layout_widget)
                );
            END
            \$\$
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("
            DO \$\$
            DECLARE
                v_layout_id BIGINT;
                v_max_id    BIGINT;
            BEGIN
                SELECT id INTO v_layout_id
                FROM page_layout
                WHERE page_key = 'fleet_map' AND customer_id IS NULL;

                IF v_layout_id IS NULL THEN
                    RAISE EXCEPTION 'fleet_map global layout not found';
                END IF;

                DELETE FROM page_layout_widget
                WHERE page_layout_id = v_layout_id
                  AND sheet_state IN ('half', 'full');

                SELECT COALESCE(MAX(id), 0) INTO v_max_id FROM page_layout_widget;

                -- Restore original half: kpi_pills (0), vehicle_info (1)
                INSERT INTO page_layout_widget (id, public_id, page_layout_id, widget_definition_id, sheet_state, position, created_at)
                SELECT v_max_id + row_number() OVER (), gen_random_uuid(), v_layout_id, wd.id, 'half', pos, NOW()
                FROM (VALUES ('kpi_pills', 0), ('vehicle_info', 1)) AS t(wtype, pos)
                JOIN widget_definition wd ON wd.type = t.wtype;

                SELECT COALESCE(MAX(id), 0) INTO v_max_id FROM page_layout_widget;

                -- Restore original full: kpi_pills (0), vehicle_info (1), driver_info (2), map_legend (3)
                INSERT INTO page_layout_widget (id, public_id, page_layout_id, widget_definition_id, sheet_state, position, created_at)
                SELECT v_max_id + row_number() OVER (), gen_random_uuid(), v_layout_id, wd.id, 'full', pos, NOW()
                FROM (VALUES ('kpi_pills', 0), ('vehicle_info', 1), ('driver_info', 2), ('map_legend', 3)) AS t(wtype, pos)
                JOIN widget_definition wd ON wd.type = t.wtype;

                PERFORM setval(
                    pg_get_serial_sequence('page_layout_widget', 'id'),
                    (SELECT MAX(id) FROM page_layout_widget)
                );
            END
            \$\$
        ");
    }
}
