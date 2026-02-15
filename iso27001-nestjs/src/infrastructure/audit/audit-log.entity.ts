import {
  Entity,
  PrimaryGeneratedColumn,
  Column,
  CreateDateColumn,
} from 'typeorm';

/**
 * A.12: Immutable append-only audit trail entity.
 * Records WHO did WHAT, WHEN, and WHICH resource was affected.
 * Records must never be updated or deleted (no UpdateDateColumn, no DeleteDateColumn).
 */
@Entity('audit_logs')
export class AuditLog {
  @PrimaryGeneratedColumn('uuid')
  id!: string;

  @Column()
  action!: string;

  @Column({ name: 'performed_by', nullable: true, type: 'varchar' })
  performedBy!: string | null;

  @Column({ name: 'resource_type' })
  resourceType!: string;

  @Column({ name: 'resource_id' })
  resourceId!: string;

  @Column({ type: 'jsonb', nullable: true })
  changes!: Record<string, unknown> | null;

  @Column({ name: 'ip_address', nullable: true, type: 'varchar' })
  ipAddress!: string | null;

  @Column({ name: 'correlation_id' })
  correlationId!: string;

  @CreateDateColumn({ name: 'created_at' })
  createdAt!: Date;
}
