<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add optimistic locking version column to route_plan.
 */
final class Version20260320000300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add version column to route_plan for optimistic locking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE route_plan ADD COLUMN version INT NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE route_plan DROP COLUMN version');
    }
}
