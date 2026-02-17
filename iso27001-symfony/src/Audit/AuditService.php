<?php

declare(strict_types=1);

namespace App\Audit;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * A.12: Immutable audit trail for compliance-critical operations.
 * Records WHO did WHAT, WHEN, and WHERE.
 */
final readonly class AuditService implements AuditServiceInterface
{
    public function __construct(
        private AuditRepository $repository,
        private LoggerInterface $auditLogger,  // Injected from 'audit' channel
        private RequestStack $requestStack,
    ) {}

    /** @param array<string, mixed> $changes */
    public function record(
        string $action,
        string $performedBy,
        string $resourceType,
        string $resourceId,
        array $changes = [],
    ): void {
        $request = $this->requestStack->getCurrentRequest();

        $entry = new AuditEntry(
            action: $action,
            performedBy: $performedBy,
            resourceType: $resourceType,
            resourceId: $resourceId,
            changes: $changes,
            ipAddress: $request?->getClientIp(),
            correlationId: $request?->attributes->get('request_id', 'system'),
        );

        $this->repository->append($entry);

        $this->auditLogger->info("audit.{$action}", [
            'performed_by' => $performedBy,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'ip_address' => $request?->getClientIp(),
        ]);
    }
}
