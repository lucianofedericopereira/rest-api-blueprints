<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\User\Events\UserDeleted;
use App\Infrastructure\Audit\AuditService;

final readonly class AuditUserDeleted
{
    public function __construct(private AuditService $auditService) {}

    public function handle(UserDeleted $event): void
    {
        $this->auditService->record(
            action:       'user.deleted',
            performedBy:  $event->userId,
            resourceType: 'user',
            resourceId:   $event->userId,
        );
    }
}
