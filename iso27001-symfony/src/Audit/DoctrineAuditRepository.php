<?php

declare(strict_types=1);

namespace App\Audit;

use Doctrine\DBAL\Connection;

/**
 * Doctrine DBAL implementation of AuditRepository.
 * Uses raw DBAL (not ORM) to guarantee append-only inserts — no accidental updates.
 */
final readonly class DoctrineAuditRepository implements AuditRepository
{
    public function __construct(private Connection $connection) {}

    public function append(AuditEntry $entry): void
    {
        $this->connection->insert('audit_logs', [
            'id' => \Symfony\Component\Uid\Uuid::v4()->toRfc4122(),
            'action' => $entry->action,
            'performed_by' => $entry->performedBy,
            'resource_type' => $entry->resourceType,
            'resource_id' => $entry->resourceId,
            'changes' => json_encode($entry->changes, JSON_THROW_ON_ERROR),
            'ip_address' => $entry->ipAddress,
            'correlation_id' => $entry->correlationId,
            'created_at' => $entry->createdAt->format('Y-m-d H:i:s.u'),
        ]);
    }
}
