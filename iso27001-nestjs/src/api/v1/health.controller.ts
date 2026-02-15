import { Controller, Get, UseGuards } from '@nestjs/common';
import { InjectDataSource } from '@nestjs/typeorm';
import { DataSource } from 'typeorm';
import { JwtAuthGuard } from '../../core/guards/jwt-auth.guard';
import { RolesGuard } from '../../core/guards/roles.guard';
import { Roles } from '../../core/decorators/roles.decorator';
import { Public } from '../../core/decorators/public.decorator';
import { errorBudget } from '../../infrastructure/telemetry/error-budget.tracker';
import {
  QualityScoreCalculator,
} from '../../infrastructure/telemetry/quality-score.calculator';
import { getMetricsText, SLO_P95_LATENCY_MS, SLO_P99_LATENCY_MS } from '../../core/metrics/prometheus.metrics';

/**
 * A.17: Health check endpoints.
 * GET /health/live   — liveness (no dependencies)
 * GET /health/ready  — readiness (DB + cache)
 * GET /health/detail — full telemetry, admin-only
 * GET /metrics       — Prometheus metrics
 */
@Controller()
@UseGuards(JwtAuthGuard, RolesGuard)
export class HealthController {
  constructor(
    @InjectDataSource() private readonly dataSource: DataSource,
  ) {}

  @Public()
  @Get('health/live')
  liveness(): { status: string; timestamp: string } {
    return { status: 'ok', timestamp: new Date().toISOString() };
  }

  @Public()
  @Get('health/ready')
  async readiness(): Promise<{ status: string; checks: Record<string, unknown>; timestamp: string }> {
    const checks: Record<string, unknown> = {};
    let overall = true;

    try {
      const start = process.hrtime.bigint();
      await this.dataSource.query('SELECT 1');
      const latencyMs = Number(process.hrtime.bigint() - start) / 1_000_000;
      checks['database'] = { status: 'ok', latency_ms: Math.round(latencyMs * 100) / 100 };
    } catch (err) {
      checks['database'] = { status: 'error', detail: (err as Error).message };
      overall = false;
    }

    return {
      status: overall ? 'ok' : 'degraded',
      checks,
      timestamp: new Date().toISOString(),
    };
  }

  @Get('health/detail')
  @Roles('admin')
  detailedHealth(): Record<string, unknown> {
    const snapshot = errorBudget.snapshot();

    const calculator = new QualityScoreCalculator(
      SLO_P95_LATENCY_MS,
      500.0,
      SLO_P99_LATENCY_MS,
    );

    const score = calculator.calculate({
      authChecksPassed: snapshot.totalRequests - snapshot.failedRequests,
      authChecksTotal: snapshot.totalRequests,
      auditEventsRecorded: snapshot.totalRequests,
      auditEventsExpected: snapshot.totalRequests,
      availability: snapshot.observedAvailability,
      logsWithCorrelationId: snapshot.totalRequests,
      totalLogs: snapshot.totalRequests,
    });

    const alert = calculator.sloAlert(
      snapshot.failedRequests,
      snapshot.clientErrors,
      snapshot.totalRequests,
    );

    return {
      status: 'ok',
      timestamp: new Date().toISOString(),
      error_budget: {
        sla_target: snapshot.slaTarget,
        total_requests: snapshot.totalRequests,
        failed_requests: snapshot.failedRequests,
        client_errors: snapshot.clientErrors,
        observed_availability: snapshot.observedAvailability,
        budget_consumed_pct: snapshot.budgetConsumedPct,
        budget_exhausted: snapshot.budgetExhausted,
      },
      slo_alerts: {
        any_breach: alert.anyBreach,
        p95_latency_breached: alert.p95LatencyBreached,
        p99_latency_breached: alert.p99LatencyBreached,
        error_rate_breached: alert.errorRateBreached,
        client_error_spike: alert.clientErrorSpike,
      },
      quality_score: score,
    };
  }

  @Public()
  @Get('metrics')
  metrics(): string {
    return getMetricsText();
  }
}
