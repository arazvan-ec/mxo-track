<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add auto_reoptimize flag to route_plan for dynamic route reoptimization (F3.1).
 */
final class Version20260309000600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add auto_reoptimize boolean to route_plan for dynamic reoptimization on exceptions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'route_plan\' AND column_name = \'auto_reoptimize\') THEN ALTER TABLE route_plan ADD auto_reoptimize BOOLEAN NOT NULL DEFAULT false; END IF; END $$');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE route_plan DROP COLUMN IF EXISTS auto_reoptimize');
    }
}
