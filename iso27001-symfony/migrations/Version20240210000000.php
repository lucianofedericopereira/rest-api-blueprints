<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial migration: creates users and audit_logs tables.
 * A.12: audit_logs is append-only — no UPDATE/DELETE triggers in application layer.
 */
final class Version20240210000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users and audit_logs tables';
    }

    public function up(Schema $schema): void
    {
        // Users table
        $this->addSql('CREATE TABLE users (
            id UUID NOT NULL,
            email VARCHAR(255) NOT NULL,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT \'ROLE_VIEWER\',
            active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(6) WITHOUT TIME ZONE DEFAULT NULL,
            deleted_at TIMESTAMP(6) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USERS_EMAIL ON users (email) WHERE deleted_at IS NULL');
        $this->addSql('CREATE INDEX IDX_USERS_ROLE ON users (role)');
        $this->addSql('CREATE INDEX IDX_USERS_DELETED_AT ON users (deleted_at)');

        // Audit logs — immutable, append-only
        $this->addSql('CREATE TABLE audit_logs (
            id UUID NOT NULL,
            action VARCHAR(100) NOT NULL,
            performed_by VARCHAR(255) NOT NULL,
            resource_type VARCHAR(100) NOT NULL,
            resource_id VARCHAR(255) NOT NULL,
            changes JSONB NOT NULL DEFAULT \'[]\',
            ip_address VARCHAR(45) DEFAULT NULL,
            correlation_id VARCHAR(36) NOT NULL,
            created_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_AUDIT_ACTION ON audit_logs (action)');
        $this->addSql('CREATE INDEX IDX_AUDIT_PERFORMED_BY ON audit_logs (performed_by)');
        $this->addSql('CREATE INDEX IDX_AUDIT_RESOURCE ON audit_logs (resource_type, resource_id)');
        $this->addSql('CREATE INDEX IDX_AUDIT_CREATED_AT ON audit_logs (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_logs');
        $this->addSql('DROP TABLE users');
    }
}
