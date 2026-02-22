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
        $this->addSql('ALTER TABLE route_stop ADD COLUMN delivery_window_start TIME DEFAULT NULL');
        $this->addSql('ALTER TABLE route_stop ADD COLUMN delivery_window_end TIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE route_stop DROP COLUMN delivery_window_start');
        $this->addSql('ALTER TABLE route_stop DROP COLUMN delivery_window_end');
    }
}
