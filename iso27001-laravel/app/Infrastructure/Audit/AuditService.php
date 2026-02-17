<?php

declare(strict_types=1);

namespace App\Infrastructure\Audit;

use App\Domain\Shared\Contracts\AuditServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

/**
 * A.12: Immutable audit trail for compliance-critical operations.
 * Records WHO did WHAT, WHEN, from WHERE.
 * Uses raw DB insert (not Eloquent) to guarantee append-only semantics.
 */
final class AuditService implements AuditServiceInterface
{
    /**
     * @param array<string, mixed> $changes
     */
    public function record(
        string $action,
        string $performedBy,
        string $resourceType,
        string $resourceId,
        array $changes = [],
    ): void {
        $correlationId = Request::header('X-Request-ID') ?? 'system';

        $entry = new AuditEntry(
            action:        $action,
            performedBy:   $performedBy,
            resourceType:  $resourceType,
            resourceId:    $resourceId,
            changes:       $changes,
            ipAddress:     Request::ip(),
            correlationId: $correlationId,
        );

        DB::table('audit_logs')->insert([
            'id'            => (string) Str::uuid(),
            'action'        => $entry->action,
            'performed_by'  => $entry->performedBy,
            'resource_type' => $entry->resourceType,
            'resource_id'   => $entry->resourceId,
            'changes'       => json_encode($entry->changes, JSON_THROW_ON_ERROR),
            'ip_address'    => $entry->ipAddress,
            'correlation_id' => $entry->correlationId,
            'created_at'    => $entry->createdAt->format('Y-m-d H:i:s.u'),
        ]);

        // A.12: Also emit to audit log channel
        Log::channel('audit')->info("audit.{$action}", [
            'performed_by'  => $performedBy,
            'resource_type' => $resourceType,
            'resource_id'   => $resourceId,
            'ip_address'    => $entry->ipAddress,
            'correlation_id' => $entry->correlationId,
        ]);
    }
}
