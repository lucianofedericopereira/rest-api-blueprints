package com.iso27001.api.infrastructure.telemetry;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;

import static org.assertj.core.api.Assertions.*;

/**
 * A.17 — Unit tests for ErrorBudgetTracker (99.9% SLA).
 */
class ErrorBudgetTrackerTest {

    private ErrorBudgetTracker tracker;

    @BeforeEach
    void setUp() {
        tracker = new ErrorBudgetTracker();
    }

    @Test
    void emptyTrackerHasFullBudget() {
        ErrorBudgetTracker.Snapshot s = tracker.snapshot();
        assertThat(s.totalRequests()).isZero();
        assertThat(s.budgetConsumedPct()).isZero();
        assertThat(s.budgetExhausted()).isFalse();
    }

    @Test
    void all2xxDoNotConsumeBudget() {
        for (int i = 0; i < 1000; i++) tracker.record(200);
        ErrorBudgetTracker.Snapshot s = tracker.snapshot();
        assertThat(s.budgetConsumedPct()).isZero();
        assertThat(s.observedAvailability()).isEqualTo(1.0);
    }

    @Test
    void oneServerErrorOn1000RequestsExhaudesBudget() {
        for (int i = 0; i < 999; i++) tracker.record(200);
        tracker.record(500); // 0.1% error rate = exactly the SLA budget
        ErrorBudgetTracker.Snapshot s = tracker.snapshot();
        assertThat(s.budgetConsumedPct()).isGreaterThanOrEqualTo(100.0);
        assertThat(s.budgetExhausted()).isTrue();
    }

    @Test
    void fourXxDoNotConsumeBudget() {
        tracker.record(404);
        tracker.record(403);
        tracker.record(401);
        ErrorBudgetTracker.Snapshot s = tracker.snapshot();
        assertThat(s.failedRequests()).isZero();
        assertThat(s.clientErrors()).isEqualTo(3);
        assertThat(s.budgetConsumedPct()).isZero();
    }

    @Test
    void resetClearsAllCounters() {
        tracker.record(500);
        tracker.reset();
        ErrorBudgetTracker.Snapshot s = tracker.snapshot();
        assertThat(s.totalRequests()).isZero();
        assertThat(s.failedRequests()).isZero();
    }
}
