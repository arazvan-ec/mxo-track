<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add reports_banner widget definition, update admin_dashboard layout to 'full' state,
 * and include reports_banner in the layout.
 */
final class Version20260408000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reports_banner widget and update admin_dashboard layout widgets to full state';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            DO \$\$
            DECLARE
                v_wd_id     BIGINT;
                v_layout_id BIGINT;
                v_plw_max   BIGINT;
            BEGIN
                -- Insert reports_banner widget definition
                SELECT COALESCE(MAX(id), 0) + 1 INTO v_wd_id FROM widget_definition;

                INSERT INTO widget_definition (id, public_id, type, label, description, preview_image, active, created_at, updated_at)
                VALUES (v_wd_id, gen_random_uuid(), 'reports_banner', 'Reports Banner', 'CTA banner linking to reports and analytics', NULL, true, NOW(), NOW());

                -- Get admin_dashboard layout id
                SELECT id INTO v_layout_id FROM page_layout
                WHERE page_key = 'admin_dashboard' AND customer_id IS NULL AND active = true
                LIMIT 1;

                IF v_layout_id IS NOT NULL THEN
                    -- Update existing 5 widgets from 'half' to 'full' state
                    UPDATE page_layout_widget
                    SET sheet_state = 'full'
                    WHERE page_layout_id = v_layout_id AND sheet_state = 'half';

                    -- Add reports_banner widget at position 5
                    SELECT COALESCE(MAX(id), 0) INTO v_plw_max FROM page_layout_widget;

                    INSERT INTO page_layout_widget (id, public_id, page_layout_id, widget_definition_id, sheet_state, position, created_at)
                    VALUES (v_plw_max + 1, gen_random_uuid(), v_layout_id, v_wd_id, 'full', 5, NOW());
                END IF;
            END
            \$\$;
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("
            DELETE FROM page_layout_widget WHERE widget_definition_id IN (
                SELECT id FROM widget_definition WHERE type = 'reports_banner'
            );
            DELETE FROM widget_definition WHERE type = 'reports_banner';
            UPDATE page_layout_widget SET sheet_state = 'half'
            WHERE page_layout_id IN (
                SELECT id FROM page_layout WHERE page_key = 'admin_dashboard'
            ) AND sheet_state = 'full';
        ");
    }
}
