<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add route_card_list widget to fleet_map layout (half + full states).
 *
 * Uses a single DO block to be atomic — previous multi-statement approach
 * left the DB in a broken state when individual INSERTs failed (DELETE
 * committed but INSERTs did not, desynchronising the BIGSERIAL sequence).
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
            BEGIN
                -- Resolve fleet_map global layout
                SELECT id INTO v_layout_id
                FROM page_layout
                WHERE page_key = 'fleet_map' AND customer_id IS NULL;

                IF v_layout_id IS NULL THEN
                    RAISE EXCEPTION 'fleet_map global layout not found';
                END IF;

                -- Remove current half and full widgets (idempotent — may already be deleted by previous failed run)
                DELETE FROM page_layout_widget
                WHERE page_layout_id = v_layout_id
                  AND sheet_state IN ('half', 'full');

                -- Fix BIGSERIAL sequence (may be desynchronised from previous failed attempts)
                PERFORM setval(
                    pg_get_serial_sequence('page_layout_widget', 'id'),
                    COALESCE((SELECT MAX(id) FROM page_layout_widget), 0)
                );

                -- half: kpi_pills (pos 0), route_card_list (pos 1)
                INSERT INTO page_layout_widget (public_id, page_layout_id, widget_definition_id, sheet_state, position, created_at)
                SELECT gen_random_uuid(), v_layout_id, wd.id, 'half', pos, NOW()
                FROM (VALUES ('kpi_pills', 0), ('route_card_list', 1)) AS t(wtype, pos)
                JOIN widget_definition wd ON wd.type = t.wtype;

                -- full: kpi_pills (0), route_card_list (1), vehicle_info (2), driver_info (3), map_legend (4)
                INSERT INTO page_layout_widget (public_id, page_layout_id, widget_definition_id, sheet_state, position, created_at)
                SELECT gen_random_uuid(), v_layout_id, wd.id, 'full', pos, NOW()
                FROM (VALUES ('kpi_pills', 0), ('route_card_list', 1), ('vehicle_info', 2), ('driver_info', 3), ('map_legend', 4)) AS t(wtype, pos)
                JOIN widget_definition wd ON wd.type = t.wtype;
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

                PERFORM setval(
                    pg_get_serial_sequence('page_layout_widget', 'id'),
                    COALESCE((SELECT MAX(id) FROM page_layout_widget), 0)
                );

                -- Restore original half: kpi_pills (0), vehicle_info (1)
                INSERT INTO page_layout_widget (public_id, page_layout_id, widget_definition_id, sheet_state, position, created_at)
                SELECT gen_random_uuid(), v_layout_id, wd.id, 'half', pos, NOW()
                FROM (VALUES ('kpi_pills', 0), ('vehicle_info', 1)) AS t(wtype, pos)
                JOIN widget_definition wd ON wd.type = t.wtype;

                -- Restore original full: kpi_pills (0), vehicle_info (1), driver_info (2), map_legend (3)
                INSERT INTO page_layout_widget (public_id, page_layout_id, widget_definition_id, sheet_state, position, created_at)
                SELECT gen_random_uuid(), v_layout_id, wd.id, 'full', pos, NOW()
                FROM (VALUES ('kpi_pills', 0), ('vehicle_info', 1), ('driver_info', 2), ('map_legend', 3)) AS t(wtype, pos)
                JOIN widget_definition wd ON wd.type = t.wtype;
            END
            \$\$
        ");
    }
}
