package com.iso27001.api.infrastructure.telemetry;

import org.springframework.stereotype.Component;

/**
 * A.17 — Quality score calculator.
 * Five weighted pillars; production gate = 0.70.
 *
 * Weights: security 40% · data_integrity 20% · reliability 15% · auditability 15% · performance 10%
 */
@Component
public class QualityScoreCalculator {

    public static final double PRODUCTION_GATE = 0.70;

    public record PillarScore(double score, double weight) {}

    public record Result(
        double composite,
        boolean passesGate,
        double productionGateThreshold,
        PillarScore security,
        PillarScore dataIntegrity,
        PillarScore reliability,
        PillarScore auditability,
        PillarScore performance
    ) {}

    public record Input(
        long authChecksPassed,
        long authChecksTotal,
        long auditEventsRecorded,
        long auditEventsExpected,
        double availability,          // 0–1
        double logsWithCorrelationId, // fraction 0–1
        double p95LatencyMs,
        double p99LatencyMs
    ) {}

    private static final double P95_TARGET_MS = 200.0;
    private static final double P99_TARGET_MS = 500.0;

    public Result calculate(Input in) {
        double security = in.authChecksTotal() == 0 ? 1.0
            : (double) in.authChecksPassed() / in.authChecksTotal();

        double dataIntegrity = in.auditEventsExpected() == 0 ? 1.0
            : (double) in.auditEventsRecorded() / in.auditEventsExpected();

        double reliability = Math.max(0.0, Math.min(1.0, in.availability()));

        double auditability = Math.max(0.0, Math.min(1.0, in.logsWithCorrelationId()));

        double latencyScore = 1.0;
        if (in.p95LatencyMs() > P95_TARGET_MS) latencyScore -= 0.5;
        if (in.p99LatencyMs() > P99_TARGET_MS) latencyScore -= 0.5;
        double performance = Math.max(0.0, latencyScore);

        double composite = security * 0.40
            + dataIntegrity * 0.20
            + reliability   * 0.15
            + auditability  * 0.15
            + performance   * 0.10;

        return new Result(
            composite,
            composite >= PRODUCTION_GATE,
            PRODUCTION_GATE,
            new PillarScore(security, 0.40),
            new PillarScore(dataIntegrity, 0.20),
            new PillarScore(reliability, 0.15),
            new PillarScore(auditability, 0.15),
            new PillarScore(performance, 0.10)
        );
    }
}
