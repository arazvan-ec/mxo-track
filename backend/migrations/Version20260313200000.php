<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260313200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create route_event table for immutable route event history';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE route_event (
                id BIGSERIAL NOT NULL,
                route_id BIGINT NOT NULL,
                event_type VARCHAR(40) NOT NULL,
                actor_type VARCHAR(20) NOT NULL,
                actor_user_id BIGINT DEFAULT NULL,
                payload JSON NOT NULL DEFAULT '{}',
                snapshot_metrics JSON DEFAULT NULL,
                occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('COMMENT ON COLUMN route_event.occurred_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN route_event.created_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE INDEX idx_route_event_route_occurred ON route_event (route_id, occurred_at)');
        $this->addSql('CREATE INDEX idx_route_event_type_occurred ON route_event (event_type, occurred_at)');

        $this->addSql('ALTER TABLE route_event ADD CONSTRAINT fk_route_event_route FOREIGN KEY (route_id) REFERENCES route_plan (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE route_event ADD CONSTRAINT fk_route_event_actor_user FOREIGN KEY (actor_user_id) REFERENCES "user_account" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE route_event');
    }
}
