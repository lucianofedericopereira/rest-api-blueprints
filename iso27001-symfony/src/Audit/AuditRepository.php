<?php

declare(strict_types=1);

namespace App\Audit;

interface AuditRepository
{
    /**
     * Append-only: audit entries are immutable.
     * A.12: No update or delete methods — compliance requirement.
     */
    public function append(AuditEntry $entry): void;
}
