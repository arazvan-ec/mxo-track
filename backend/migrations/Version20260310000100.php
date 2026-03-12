<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create ml_embedding table for semantic search via pgvector.
 */
final class Version20260310000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enable pgvector extension and create ml_embedding table for semantic search';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS vector');

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS ml_embedding (
                id BIGSERIAL PRIMARY KEY,
                entity_type VARCHAR(50) NOT NULL,
                entity_id BIGINT NOT NULL,
                embedding vector(1536) NOT NULL,
                text_content TEXT NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW()
            )
        SQL);

        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ml_embedding_entity ON ml_embedding (entity_type, entity_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ml_embedding_entity_type ON ml_embedding (entity_type)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS ml_embedding');
    }
}
