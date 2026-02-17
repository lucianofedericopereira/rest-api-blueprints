/**
 * Unit tests for ErrorBudgetTracker.
 * Mirrors iso27001-fastapi/tests/unit/test_error_budget.py (6 tests).
 */
import { ErrorBudgetTracker } from '../../src/infrastructure/telemetry/error-budget.tracker';

describe('ErrorBudgetTracker (A.17)', () => {
  it('returns zero consumed with no requests', () => {
    const tracker = new ErrorBudgetTracker(0.999);
    const snap = tracker.snapshot();
    expect(snap.totalRequests).toBe(0);
    expect(snap.budgetConsumedPct).toBe(0.0);
    expect(snap.budgetExhausted).toBe(false);
  });

  it('all 2xx requests consume no budget', () => {
    const tracker = new ErrorBudgetTracker(0.999);
    for (let i = 0; i < 1000; i++) tracker.record(200);
    const snap = tracker.snapshot();
    expect(snap.failedRequests).toBe(0);
    expect(snap.budgetConsumedPct).toBe(0.0);
    expect(snap.observedAvailability).toBe(1.0);
  });

  it('one 5xx in 1000 requests exhausts the 99.9% budget', () => {
    const tracker = new ErrorBudgetTracker(0.999);
    for (let i = 0; i < 999; i++) tracker.record(200);
    tracker.record(500);
    const snap = tracker.snapshot();
    expect(snap.failedRequests).toBe(1);
    expect(snap.budgetConsumedPct).toBe(100.0);
    expect(snap.budgetExhausted).toBe(true);
  });

  it('4xx errors do not consume the 5xx budget', () => {
    const tracker = new ErrorBudgetTracker(0.999);
    for (let i = 0; i < 100; i++) tracker.record(404);
    const snap = tracker.snapshot();
    expect(snap.failedRequests).toBe(0);
    expect(snap.clientErrors).toBe(100);
    expect(snap.budgetConsumedPct).toBe(0.0);
  });

  it('reset clears all counters', () => {
    const tracker = new ErrorBudgetTracker(0.999);
    tracker.record(500);
    tracker.reset();
    const snap = tracker.snapshot();
    expect(snap.totalRequests).toBe(0);
    expect(snap.failedRequests).toBe(0);
  });

  it('throws when slaTarget is out of (0, 1) range', () => {
    expect(() => new ErrorBudgetTracker(1.0)).toThrow();
    expect(() => new ErrorBudgetTracker(0.0)).toThrow();
  });
});
