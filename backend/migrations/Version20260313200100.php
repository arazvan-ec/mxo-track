<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260313200100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add etas JSON column to route_snapshot for reactive ETA persistence';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE route_snapshot ADD etas JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE route_snapshot DROP COLUMN etas');
    }
}
