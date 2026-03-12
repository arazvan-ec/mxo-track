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
        // pgvector may not be available on all PostgreSQL hosts (e.g. Railway).
        // Skip silently if the extension cannot be created.
        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                CREATE EXTENSION IF NOT EXISTS vector;
            EXCEPTION WHEN OTHERS THEN
                RAISE NOTICE 'pgvector not available, skipping ml_embedding table';
                RETURN;
            END $$
        SQL);

        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'vector') THEN
                    CREATE TABLE IF NOT EXISTS ml_embedding (
                        id BIGSERIAL PRIMARY KEY,
                        entity_type VARCHAR(50) NOT NULL,
                        entity_id BIGINT NOT NULL,
                        embedding vector(1536) NOT NULL,
                        text_content TEXT NOT NULL,
                        updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW()
                    );
                    CREATE UNIQUE INDEX IF NOT EXISTS uniq_ml_embedding_entity ON ml_embedding (entity_type, entity_id);
                    CREATE INDEX IF NOT EXISTS idx_ml_embedding_entity_type ON ml_embedding (entity_type);
                END IF;
            END $$
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS ml_embedding');
    }
}
