import 'reflect-metadata';
import { NestFactory, Reflector } from '@nestjs/core';
import { ValidationPipe } from '@nestjs/common';
import { AppModule } from './app.module';
import { loadConfig } from './core/config/configuration';

async function bootstrap(): Promise<void> {
  const config = loadConfig();

  const app = await NestFactory.create(AppModule, {
    // A.14: suppress NestJS default exception details from console in production
    logger: config.appEnv === 'production' ? ['error', 'warn'] : ['log', 'error', 'warn', 'debug'],
  });

  // A.14: Global validation pipe — rejects requests with invalid payloads
  app.useGlobalPipes(
    new ValidationPipe({
      whitelist: true,         // strip unknown properties
      forbidNonWhitelisted: true,
      transform: true,
      transformOptions: { enableImplicitConversion: false },
    }),
  );

  // A.9: CORS — explicit allowlist, no wildcard
  app.enableCors({
    origin: config.corsAllowedOrigins,
    methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    allowedHeaders: ['Authorization', 'Content-Type', 'X-Request-ID'],
    exposedHeaders: ['X-Request-ID', 'X-Response-Time'],
  });

  // Disable X-Powered-By header (A.14: do not expose tech stack)
  app.getHttpAdapter().getInstance().disable('x-powered-by');

  await app.listen(config.appPort);
  console.log(
    JSON.stringify({
      timestamp: new Date().toISOString(),
      level: 'INFO',
      message: 'server.started',
      service: config.appName,
      environment: config.appEnv,
      context: { port: config.appPort },
    }),
  );
}

bootstrap().catch((err: unknown) => {
  console.error('Failed to start server:', err);
  process.exit(1);
});
