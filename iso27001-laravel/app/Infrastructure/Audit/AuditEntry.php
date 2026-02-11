<?php

declare(strict_types=1);

namespace App\Infrastructure\Audit;

/**
 * Immutable value object for a single audit record.
 * A.12: Append-only — no modification after construction.
 */
final readonly class AuditEntry
{
    public function __construct(
        public string $action,
        public string $performedBy,
        public string $resourceType,
        public string $resourceId,
        public array $changes,
        public ?string $ipAddress,
        public string $correlationId,
        public \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {}
}
