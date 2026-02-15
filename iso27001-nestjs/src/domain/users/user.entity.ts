import {
  Entity,
  PrimaryGeneratedColumn,
  Column,
  CreateDateColumn,
  UpdateDateColumn,
  DeleteDateColumn,
} from 'typeorm';

export type UserRole = 'admin' | 'manager' | 'analyst' | 'viewer';

/**
 * A.9: RBAC hierarchy — admin > manager > analyst > viewer
 * Higher index = higher privilege.
 */
export const ROLE_HIERARCHY: Record<UserRole, number> = {
  viewer: 0,
  analyst: 1,
  manager: 2,
  admin: 3,
};

@Entity('users')
export class User {
  @PrimaryGeneratedColumn('uuid')
  id!: string;

  /** A.10: Stored encrypted at rest via FieldEncryptor (see subscriber). */
  @Column({ unique: true })
  email!: string;

  @Column({ name: 'hashed_password' })
  hashedPassword!: string;

  /** A.10: Stored encrypted at rest via FieldEncryptor. */
  @Column({ name: 'full_name', nullable: true, type: 'varchar' })
  fullName!: string | null;

  @Column({ type: 'varchar', default: 'viewer' })
  role!: UserRole;

  @Column({ name: 'is_active', default: true })
  isActive!: boolean;

  /** A.12: Audit timestamps */
  @CreateDateColumn({ name: 'created_at' })
  createdAt!: Date;

  @UpdateDateColumn({ name: 'updated_at' })
  updatedAt!: Date;

  /** A.12: SoftDelete — records never physically removed */
  @DeleteDateColumn({ name: 'deleted_at', nullable: true })
  deletedAt!: Date | null;
}
