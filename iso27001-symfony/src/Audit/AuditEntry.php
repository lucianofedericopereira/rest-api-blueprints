<?php

declare(strict_types=1);

namespace App\Audit;

final readonly class AuditEntry
{
    public function __construct(
        public string $action,
        public string $performedBy,
        public string $resourceType,
        public string $resourceId,
        /** @var array<string, mixed> */
        public array $changes,
        public ?string $ipAddress,
        public string $correlationId,
        public \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {}
}
