import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { OnEvent } from '@nestjs/event-emitter';
import { AuditLog } from './audit-log.entity';
import { UserCreatedEvent } from '../../domain/users/events/user-created.event';
import { correlationIdStorage } from '../../core/middleware/correlation-id.middleware';

export interface AuditRecordOptions {
  action: string;
  resourceType: string;
  resourceId: string;
  performedBy?: string;
  changes?: Record<string, unknown>;
  ipAddress?: string;
  correlationId?: string;
}

@Injectable()
export class AuditService {
  constructor(
    @InjectRepository(AuditLog)
    private readonly repo: Repository<AuditLog>,
  ) {}

  async record(opts: AuditRecordOptions): Promise<void> {
    try {
      const entry = this.repo.create({
        action: opts.action,
        performedBy: opts.performedBy ?? 'system',
        resourceType: opts.resourceType,
        resourceId: opts.resourceId,
        changes: opts.changes ?? null,
        ipAddress: opts.ipAddress ?? null,
        correlationId: opts.correlationId ?? correlationIdStorage.getStore() ?? 'unknown',
      });
      await this.repo.save(entry);
    } catch {
      // Best-effort — never let audit failure crash the main request
    }
  }

  /** A.12: Domain event listener — routes UserCreated to audit table */
  @OnEvent('user.created')
  async onUserCreated(event: UserCreatedEvent): Promise<void> {
    await this.record({
      action: 'user.created',
      resourceType: 'user',
      resourceId: event.userId,
      performedBy: 'system',
      changes: { email_hash: event.emailHash, role: event.role },
    });
  }
}
