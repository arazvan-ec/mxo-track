<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * AI integration fields: shipment description for skill detection (B3), route AI analysis (B6).
 */
final class Version20260306000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shipment description (B3 skill detection) and route ai_analysis (B6 post-route analysis)';
    }

    public function up(Schema $schema): void
    {
        // B3: Shipment description for AI skill detection
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'shipment\' AND column_name = \'description\') THEN ALTER TABLE shipment ADD COLUMN description TEXT DEFAULT NULL; END IF; END $$');

        // B6: Route AI analysis JSON field
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'route_plan\' AND column_name = \'ai_analysis\') THEN ALTER TABLE route_plan ADD COLUMN ai_analysis JSON DEFAULT NULL; END IF; END $$');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shipment DROP COLUMN IF EXISTS description');
        $this->addSql('ALTER TABLE route_plan DROP COLUMN IF EXISTS ai_analysis');
    }
}
