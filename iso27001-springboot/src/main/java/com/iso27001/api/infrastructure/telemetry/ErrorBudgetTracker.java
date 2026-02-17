package com.iso27001.api.infrastructure.telemetry;

import org.springframework.stereotype.Component;
import java.util.concurrent.atomic.AtomicLong;

/**
 * A.17 — Error budget tracker (99.9% SLA).
 * 5xx responses consume budget; 4xx tracked separately but do not count against SLA.
 */
@Component
public class ErrorBudgetTracker {

    private static final double SLA_TARGET = 0.999;

    private final AtomicLong total = new AtomicLong(0);
    private final AtomicLong failed = new AtomicLong(0);   // 5xx
    private final AtomicLong clientErrors = new AtomicLong(0); // 4xx

    public void record(int statusCode) {
        total.incrementAndGet();
        if (statusCode >= 500) {
            failed.incrementAndGet();
        } else if (statusCode >= 400) {
            clientErrors.incrementAndGet();
        }
    }

    public record Snapshot(
        double slaTarget,
        long totalRequests,
        long failedRequests,
        long clientErrors,
        double observedAvailability,
        double budgetConsumedPct,
        boolean budgetExhausted
    ) {}

    public Snapshot snapshot() {
        long t = total.get();
        long f = failed.get();
        long ce = clientErrors.get();

        double availability = t == 0 ? 1.0 : (double)(t - f) / t;
        // Compute consumed budget in integer-scaled arithmetic first to avoid
        // IEEE 754 precision loss (e.g. 1/1000 / 0.001 * 100 ≈ 99.9999…).
        // consumed% = (f / t) / (1 - SLA) * 100  =  f * 100_000 / (t * (1 - SLA) * 1000)
        // Simplified: consumed = f * 100.0 / (t * allowedErrorRate)
        double allowedErrorRate = 1.0 - SLA_TARGET;        // 0.001 for 99.9 % SLA
        double consumed = (t == 0 || allowedErrorRate == 0)
            ? 0.0
            : Math.round((double) f * 100_000.0 / (t * allowedErrorRate)) / 1000.0;

        return new Snapshot(SLA_TARGET, t, f, ce, availability, consumed, consumed >= 100.0);
    }

    public void reset() {
        total.set(0);
        failed.set(0);
        clientErrors.set(0);
    }
}
