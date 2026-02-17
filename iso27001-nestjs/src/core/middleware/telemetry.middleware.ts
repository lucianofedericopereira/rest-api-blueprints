import { Injectable, NestMiddleware, Logger } from '@nestjs/common';
import { Request, Response, NextFunction } from 'express';
import { errorBudget } from '../../infrastructure/telemetry/error-budget.tracker';
import { cwEmitter, xray } from '../../infrastructure/telemetry/cloudwatch-emitter';
import { recordRequest } from '../metrics/prometheus.metrics';
import { getCorrelationId } from './correlation-id.middleware';

/**
 * A.12 / A.17: Per-request telemetry middleware.
 * Records:
 *   - Structured JSON log for request start and completion
 *   - Prometheus-style counters (error budget)
 *   - CloudWatch custom metrics (no-op without credentials)
 *   - X-Response-Time header
 */
@Injectable()
export class TelemetryMiddleware implements NestMiddleware {
  private readonly logger = new Logger('HTTP');

  use(req: Request, res: Response, next: NextFunction): void {
    const start = process.hrtime.bigint();

    // A.12: X-Ray trace header propagation (no-op when header is absent)
    const traceId = xray.extractTraceId(req.headers as Record<string, string | string[] | undefined>);

    this.logger.log(
      JSON.stringify({
        timestamp: new Date().toISOString(),
        level: 'INFO',
        message: 'request.started',
        service: process.env.APP_NAME ?? 'iso27001-api',
        environment: process.env.APP_ENV ?? 'development',
        request_id: getCorrelationId(),
        context: { method: req.method, path: req.path },
      }),
    );

    res.on('finish', () => {
      const durationMs = Number(process.hrtime.bigint() - start) / 1_000_000;
      const rounded = Math.round(durationMs * 100) / 100;

      // A.17: Record in error budget (5xx consumes budget; 4xx tracked separately)
      errorBudget.record(res.statusCode);

      // A.17: Prometheus counters + latency histogram
      recordRequest(req.method, req.path, res.statusCode, rounded);

      // CloudWatch (no-op without credentials)
      cwEmitter.emitRequest(req.method, req.path, res.statusCode, rounded);

      res.setHeader('X-Response-Time', `${rounded}ms`);
      if (traceId) res.setHeader('X-Amzn-Trace-Id', traceId);

      this.logger.log(
        JSON.stringify({
          timestamp: new Date().toISOString(),
          level: 'INFO',
          message: 'request.completed',
          service: process.env.APP_NAME ?? 'iso27001-api',
          environment: process.env.APP_ENV ?? 'development',
          request_id: getCorrelationId(),
          ...(traceId && { trace_id: traceId }),
          context: {
            method: req.method,
            path: req.path,
            status_code: res.statusCode,
            duration_ms: rounded,
          },
        }),
      );
    });

    next();
  }
}
