/**
 * Unit tests for in-process Prometheus metrics.
 * Validates counter and histogram correctness.
 */
import {
  recordRequest,
  getMetricsText,
} from '../../src/core/metrics/prometheus.metrics';

describe('Prometheus metrics (A.17)', () => {
  it('getMetricsText includes service info gauge', () => {
    const text = getMetricsText();
    expect(text).toContain('iso27001_info');
    expect(text).toContain('iso27001-nestjs');
  });

  it('recordRequest increments http_requests_total counter', () => {
    recordRequest('GET', '/health/live', 200, 5.0);
    const text = getMetricsText();
    expect(text).toContain('http_requests_total');
    expect(text).toContain('"GET"');
    expect(text).toContain('2xx');
  });

  it('5xx requests appear as 5xx status class', () => {
    recordRequest('POST', '/api/v1/auth/login', 500, 12.0);
    const text = getMetricsText();
    expect(text).toContain('5xx');
  });

  it('histogram buckets appear in output', () => {
    recordRequest('GET', '/api/v1/users', 200, 42.0);
    const text = getMetricsText();
    expect(text).toContain('http_request_duration_ms_bucket');
    expect(text).toContain('http_request_duration_ms_sum');
    expect(text).toContain('http_request_duration_ms_count');
  });

  it('output is valid Prometheus text format (# HELP / # TYPE lines)', () => {
    const text = getMetricsText();
    expect(text).toContain('# HELP');
    expect(text).toContain('# TYPE');
  });
});
