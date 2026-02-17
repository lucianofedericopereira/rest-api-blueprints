import type { StringValue } from 'ms';

export interface AppConfig {
  appName: string;
  appEnv: string;
  appPort: number;
  databaseUrl: string;
  redisUrl: string;
  jwtSecret: string;
  jwtAccessExpiresIn: StringValue;
  jwtRefreshExpiresIn: StringValue;
  encryptionKey: string;
  corsAllowedOrigins: string[];
}

export function loadConfig(): AppConfig {
  return {
    appName: process.env.APP_NAME ?? 'iso27001-api',
    appEnv: process.env.APP_ENV ?? 'development',
    appPort: parseInt(process.env.APP_PORT ?? '3000', 10),
    databaseUrl: process.env.DATABASE_URL ?? 'postgresql://user:pass@localhost:5432/iso27001',
    redisUrl: process.env.REDIS_URL ?? 'redis://localhost:6379/0',
    jwtSecret: process.env.JWT_SECRET ?? 'change-me',
    jwtAccessExpiresIn: (process.env.JWT_ACCESS_EXPIRES_IN ?? '30m') as StringValue,
    jwtRefreshExpiresIn: (process.env.JWT_REFRESH_EXPIRES_IN ?? '7d') as StringValue,
    encryptionKey: process.env.ENCRYPTION_KEY ?? 'change-me-to-exactly-32-bytes!!',
    corsAllowedOrigins: (process.env.CORS_ALLOWED_ORIGINS ?? 'http://localhost:3000')
      .split(',')
      .map((o) => o.trim())
      .filter(Boolean),
  };
}
