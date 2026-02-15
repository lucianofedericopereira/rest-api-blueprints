/**
 * Minimal Prometheus-compatible metrics stub.
 *
 * For production use, install prom-client:
 *   npm install prom-client
 * and replace this file with real counters/histograms.
 *
 * The /metrics endpoint is wired in HealthController.
 */
export const SLO_P95_LATENCY_MS = 200.0;
export const SLO_P99_LATENCY_MS = 500.0;

export function getMetricsText(): string {
  return [
    '# HELP iso27001_info Service information',
    '# TYPE iso27001_info gauge',
    `iso27001_info{service="iso27001-nestjs",version="1.0.0"} 1`,
    '',
  ].join('\n');
}
