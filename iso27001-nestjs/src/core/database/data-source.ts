import { DataSource } from 'typeorm';
import { User } from '../../domain/users/user.entity';
import { AuditLog } from '../../infrastructure/audit/audit-log.entity';

const databaseUrl = process.env.DATABASE_URL ?? 'postgresql://user:pass@localhost:5432/iso27001';

export const AppDataSource = new DataSource({
  type: 'postgres',
  url: databaseUrl,
  entities: [User, AuditLog],
  synchronize: process.env.APP_ENV !== 'production', // use migrations in production
  logging: process.env.APP_ENV === 'development',
});
