<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Infrastructure\Security\CredentialEncryptor;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260320000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Encrypt existing CustomerIntegration config data and change column type to TEXT';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_integration ADD config_encrypted TEXT DEFAULT NULL');

        // Encrypt existing data in PHP
        $rows = $this->connection->fetchAllAssociative('SELECT id, config FROM customer_integration');
        $encryptor = new CredentialEncryptor($_ENV['APP_SECRET'] ?? $_SERVER['APP_SECRET'] ?? '');

        foreach ($rows as $row) {
            $config = json_decode($row['config'], true) ?? [];
            $encrypted = $encryptor->encrypt($config);
            $this->connection->executeStatement(
                'UPDATE customer_integration SET config_encrypted = ? WHERE id = ?',
                [$encrypted, $row['id']]
            );
        }

        $this->addSql('ALTER TABLE customer_integration DROP COLUMN config');
        $this->addSql('ALTER TABLE customer_integration RENAME COLUMN config_encrypted TO config');
        $this->addSql('ALTER TABLE customer_integration ALTER COLUMN config SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_integration ADD config_plain JSONB DEFAULT NULL');

        $rows = $this->connection->fetchAllAssociative('SELECT id, config FROM customer_integration');
        $encryptor = new CredentialEncryptor($_ENV['APP_SECRET'] ?? $_SERVER['APP_SECRET'] ?? '');

        foreach ($rows as $row) {
            try {
                $config = $encryptor->decrypt($row['config']);
            } catch (\RuntimeException) {
                $config = [];
            }
            $this->connection->executeStatement(
                'UPDATE customer_integration SET config_plain = ? WHERE id = ?',
                [json_encode($config), $row['id']]
            );
        }

        $this->addSql('ALTER TABLE customer_integration DROP COLUMN config');
        $this->addSql('ALTER TABLE customer_integration RENAME COLUMN config_plain TO config');
        $this->addSql('ALTER TABLE customer_integration ALTER COLUMN config SET NOT NULL');
        $this->addSql('ALTER TABLE customer_integration ALTER COLUMN config TYPE JSONB USING config::jsonb');
    }
}
