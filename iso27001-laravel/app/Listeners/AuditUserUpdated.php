<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\User\Events\UserUpdated;
use App\Domain\Shared\Contracts\AuditServiceInterface;

final readonly class AuditUserUpdated
{
    public function __construct(private AuditServiceInterface $auditService) {}

    public function handle(UserUpdated $event): void
    {
        $this->auditService->record(
            action:       'user.updated',
            performedBy:  $event->userId,
            resourceType: 'user',
            resourceId:   $event->userId,
            changes:      ['fields_changed' => $event->changes],
        );
    }
}
