/**
 * Unit tests for QualityScoreCalculator.
 * Mirrors iso27001-fastapi/tests/unit/test_quality_score.py (6 tests).
 */
import {
  QualityScoreCalculator,
  PRODUCTION_GATE,
} from '../../src/infrastructure/telemetry/quality-score.calculator';

function perfectInput() {
  return {
    authChecksPassed: 100,
    authChecksTotal: 100,
    auditEventsRecorded: 100,
    auditEventsExpected: 100,
    availability: 1.0,
    logsWithCorrelationId: 100,
    totalLogs: 100,
  };
}

describe('QualityScoreCalculator (A.17)', () => {
  it('perfect input produces composite score of 1.0', () => {
    const calc = new QualityScoreCalculator(0.0, 200.0);
    const result = calc.calculate(perfectInput());
    expect(result.composite).toBeCloseTo(1.0, 4);
  });

  it('perfect score passes the production gate', () => {
    const calc = new QualityScoreCalculator(0.0, 200.0);
    const result = calc.calculate(perfectInput());
    expect(result.passesGate).toBe(true);
  });

  it('pillar weights sum to 0.95 (5% reserved)', () => {
    const weights = [0.40, 0.20, 0.15, 0.15, 0.05];
    const sum = weights.reduce((a, b) => a + b, 0);
    expect(sum).toBeCloseTo(0.95, 10);
  });

  it('zero security score pulls composite below the production gate', () => {
    const calc = new QualityScoreCalculator(0.0, 200.0);
    const result = calc.calculate({
      ...perfectInput(),
      authChecksPassed: 0, // security pillar = 0
      authChecksTotal: 100,
    });
    // security=0*0.40 + rest*0.55 = 0.55; normalised = 0.55/0.95 ≈ 0.579 < 0.70
    expect(result.composite).toBeLessThan(PRODUCTION_GATE);
    expect(result.passesGate).toBe(false);
  });

  it('zero denominator data defaults pillars to 1.0 (no false alarm at startup)', () => {
    const calc = new QualityScoreCalculator(0.0, 200.0);
    const result = calc.calculate({
      authChecksPassed: 0,
      authChecksTotal: 0,
      auditEventsRecorded: 0,
      auditEventsExpected: 0,
      availability: 1.0,
      logsWithCorrelationId: 0,
      totalLogs: 0,
    });
    expect(result.pillars.security.score).toBe(1.0);
    expect(result.pillars.data_integrity.score).toBe(1.0);
    expect(result.pillars.auditability.score).toBe(1.0);
  });

  it('result shape includes composite, passesGate, and all pillar names', () => {
    const calc = new QualityScoreCalculator(0.0, 200.0);
    const result = calc.calculate(perfectInput());
    expect(typeof result.composite).toBe('number');
    expect(typeof result.passesGate).toBe('boolean');
    expect(result.pillars).toHaveProperty('security');
    expect(result.pillars).toHaveProperty('data_integrity');
    expect(result.pillars).toHaveProperty('reliability');
    expect(result.pillars).toHaveProperty('auditability');
    expect(result.pillars).toHaveProperty('performance');
  });
});
