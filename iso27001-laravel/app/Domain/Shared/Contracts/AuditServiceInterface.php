<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

interface AuditServiceInterface
{
    /** @param array<string, mixed> $changes */
    public function record(
        string $action,
        string $performedBy,
        string $resourceType,
        string $resourceId,
        array $changes = [],
    ): void;
}
