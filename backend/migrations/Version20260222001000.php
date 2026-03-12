<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222001000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add delivery_window_start and delivery_window_end to route_stop table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'route_stop\' AND column_name = \'delivery_window_start\') THEN ALTER TABLE route_stop ADD COLUMN delivery_window_start TIME DEFAULT NULL; END IF; END $$');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'route_stop\' AND column_name = \'delivery_window_end\') THEN ALTER TABLE route_stop ADD COLUMN delivery_window_end TIME DEFAULT NULL; END IF; END $$');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE route_stop DROP COLUMN delivery_window_start');
        $this->addSql('ALTER TABLE route_stop DROP COLUMN delivery_window_end');
    }
}
