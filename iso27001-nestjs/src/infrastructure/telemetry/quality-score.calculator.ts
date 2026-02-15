/**
 * A.17: Risk-weighted Quality Score Calculator.
 *
 * Composite score derived from five pillars aligned to ISO 27001 domains:
 *   Pillar           Weight   ISO 27001 Annex
 *   Security          40%     A.9, A.10
 *   Data Integrity    20%     A.12
 *   Reliability       15%     A.17
 *   Auditability      15%     A.12
 *   Performance        5%     A.17
 *   (Bonus gap)        5%     reserved
 *
 * Score of 1.0 = perfect compliance; 0.0 = complete failure.
 * A score below 0.70 should block production deployments.
 */

export const PRODUCTION_GATE = 0.70;

export interface QualityScoreResult {
  composite: number;
  passesGate: boolean;
  productionGateThreshold: number;
  pillars: {
    security: { score: number; weight: number };
    data_integrity: { score: number; weight: number };
    reliability: { score: number; weight: number };
    auditability: { score: number; weight: number };
    performance: { score: number; weight: number };
  };
}

export interface SloAlert {
  anyBreach: boolean;
  p95LatencyBreached: boolean;
  p99LatencyBreached: boolean;
  errorRateBreached: boolean;
  clientErrorSpike: boolean;
}

export interface CalculateInput {
  authChecksPassed: number;
  authChecksTotal: number;
  auditEventsRecorded: number;
  auditEventsExpected: number;
  availability: number;
  logsWithCorrelationId: number;
  totalLogs: number;
}

export class QualityScoreCalculator {
  static readonly SLO_P95_LATENCY_MS = 200.0;
  static readonly SLO_P99_LATENCY_MS = 500.0;
  static readonly SLO_ERROR_RATE_PCT = 0.1;
  static readonly CLIENT_ERROR_SPIKE_PCT = 5.0;

  constructor(
    private readonly slaLatencyP95Ms: number = 200.0,
    private readonly targetLatencyMs: number = 500.0,
    private readonly slaLatencyP99Ms: number = 0.0,
  ) {}

  calculate(input: CalculateInput): QualityScoreResult {
    const security = this.ratio(input.authChecksPassed, input.authChecksTotal);
    const dataIntegrity = this.ratio(input.auditEventsRecorded, input.auditEventsExpected);
    const reliability = Math.max(0, Math.min(1, input.availability));
    const auditability = this.ratio(input.logsWithCorrelationId, input.totalLogs);
    const performance = this.latencyScore();

    const WEIGHT_SUM = 0.95;
    const raw =
      security * 0.40 +
      dataIntegrity * 0.20 +
      reliability * 0.15 +
      auditability * 0.15 +
      performance * 0.05;
    const composite = Math.round((raw / WEIGHT_SUM) * 10_000) / 10_000;

    return {
      composite,
      passesGate: composite >= PRODUCTION_GATE,
      productionGateThreshold: PRODUCTION_GATE,
      pillars: {
        security: { score: security, weight: 0.40 },
        data_integrity: { score: dataIntegrity, weight: 0.20 },
        reliability: { score: reliability, weight: 0.15 },
        auditability: { score: auditability, weight: 0.15 },
        performance: { score: performance, weight: 0.05 },
      },
    };
  }

  sloAlert(failedRequests: number, clientErrors: number, totalRequests: number): SloAlert {
    const errorRatePct = totalRequests > 0 ? (failedRequests / totalRequests) * 100 : 0;
    const clientErrorPct = totalRequests > 0 ? (clientErrors / totalRequests) * 100 : 0;
    const p95Breached = this.slaLatencyP95Ms > QualityScoreCalculator.SLO_P95_LATENCY_MS;
    const p99Breached = this.slaLatencyP99Ms > QualityScoreCalculator.SLO_P99_LATENCY_MS;
    const errorBreached = errorRatePct > QualityScoreCalculator.SLO_ERROR_RATE_PCT;
    const clientSpike = clientErrorPct > QualityScoreCalculator.CLIENT_ERROR_SPIKE_PCT;
    return {
      anyBreach: p95Breached || p99Breached || errorBreached || clientSpike,
      p95LatencyBreached: p95Breached,
      p99LatencyBreached: p99Breached,
      errorRateBreached: errorBreached,
      clientErrorSpike: clientSpike,
    };
  }

  private ratio(numerator: number, denominator: number): number {
    if (denominator <= 0) return 1.0;
    return Math.max(0, Math.min(1, numerator / denominator));
  }

  private latencyScore(): number {
    if (this.targetLatencyMs <= 0) return 1.0;
    return Math.max(0, 1 - this.slaLatencyP95Ms / this.targetLatencyMs);
  }
}
