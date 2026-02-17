import { Module, MiddlewareConsumer, NestModule, RequestMethod } from '@nestjs/common';
import { APP_GUARD, APP_FILTER } from '@nestjs/core';
import { TypeOrmModule } from '@nestjs/typeorm';
import { JwtModule } from '@nestjs/jwt';
import { PassportModule } from '@nestjs/passport';
import { ThrottlerModule, ThrottlerGuard } from '@nestjs/throttler';
import { EventEmitterModule } from '@nestjs/event-emitter';

import { loadConfig } from './core/config/configuration';
import { User } from './domain/users/user.entity';
import { AuditLog } from './infrastructure/audit/audit-log.entity';

import { UserService } from './domain/users/user.service';
import { USER_REPOSITORY } from './domain/users/user-repository.interface';
import { TypeOrmUserRepository } from './infrastructure/repositories/typeorm-user.repository';
import { FieldEncryptor } from './infrastructure/encryption/field-encryptor';
import { BruteForceGuard } from './infrastructure/security/brute-force.guard';
import { AuditService } from './infrastructure/audit/audit.service';

import { JwtStrategy } from './core/auth/jwt.strategy';
import { JwtAuthGuard } from './core/guards/jwt-auth.guard';
import { RolesGuard } from './core/guards/roles.guard';
import { AllExceptionsFilter } from './core/filters/all-exceptions.filter';
import { CorrelationIdMiddleware } from './core/middleware/correlation-id.middleware';
import { SecurityHeadersMiddleware } from './core/middleware/security-headers.middleware';
import { TelemetryMiddleware } from './core/middleware/telemetry.middleware';

import { AuthController } from './api/v1/auth.controller';
import { UsersController } from './api/v1/users.controller';
import { HealthController } from './api/v1/health.controller';

import Redis from 'ioredis';

const config = loadConfig();

/** Try to connect to Redis; return null if unavailable (graceful fallback). */
function createRedisClient(): Redis | null {
  try {
    const client = new Redis(config.redisUrl, { lazyConnect: true, connectTimeout: 1000 });
    return client;
  } catch {
    return null;
  }
}

@Module({
  imports: [
    // Database
    TypeOrmModule.forRoot({
      type: 'postgres',
      url: config.databaseUrl,
      entities: [User, AuditLog],
      synchronize: config.appEnv !== 'production',
      logging: config.appEnv === 'development',
    }),
    TypeOrmModule.forFeature([User, AuditLog]),

    // Domain events
    EventEmitterModule.forRoot(),

    // A.9: JWT
    PassportModule,
    JwtModule.register({
      secret: config.jwtSecret,
      signOptions: { expiresIn: config.jwtAccessExpiresIn },
    }),

    // A.9: Tiered rate limiting
    // auth: 10/min, write: 30/min, global: 100/min
    // NestJS Throttler applies the most restrictive matching TTL+limit pair.
    ThrottlerModule.forRoot([
      { name: 'global', ttl: 60_000, limit: 100 },
      { name: 'write',  ttl: 60_000, limit: 30 },
      { name: 'auth',   ttl: 60_000, limit: 10 },
    ]),
  ],
  controllers: [AuthController, UsersController, HealthController],
  providers: [
    // Domain
    UserService,
    { provide: USER_REPOSITORY, useClass: TypeOrmUserRepository },

    // Infrastructure
    AuditService,
    {
      provide: FieldEncryptor,
      useFactory: () => new FieldEncryptor(config.encryptionKey),
    },
    {
      provide: BruteForceGuard,
      useFactory: () => new BruteForceGuard(createRedisClient()),
    },

    // Auth
    JwtStrategy,

    // Global guards (applied to every route via APP_GUARD)
    { provide: APP_GUARD, useClass: JwtAuthGuard },
    { provide: APP_GUARD, useClass: RolesGuard },
    { provide: APP_GUARD, useClass: ThrottlerGuard },

    // Global exception filter (A.14: no stack traces to clients)
    { provide: APP_FILTER, useClass: AllExceptionsFilter },
  ],
})
export class AppModule implements NestModule {
  configure(consumer: MiddlewareConsumer): void {
    consumer
      .apply(CorrelationIdMiddleware, SecurityHeadersMiddleware, TelemetryMiddleware)
      .forRoutes({ path: '{*path}', method: RequestMethod.ALL });
  }
}
