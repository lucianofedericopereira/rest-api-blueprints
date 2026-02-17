/**
 * A.17: In-process Prometheus-compatible metrics.
 *
 * Implements counters and a fixed-bucket histogram with zero external
 * dependencies. The output format is valid Prometheus text exposition 0.0.4,
 * consumable by any Prometheus scraper.
 *
 * For higher-cardinality or multi-process accuracy, replace with prom-client:
 *   npm install prom-client
 */

export const SLO_P95_LATENCY_MS = 200.0;
export const SLO_P99_LATENCY_MS = 500.0;

// ---------------------------------------------------------------------------
// Internal state — module-level singletons (reset on process restart)
// ---------------------------------------------------------------------------

/** http_requests_total{method, status_class} */
const requestCounts = new Map<string, number>();

/** http_request_duration_ms histogram — fixed buckets (ms) */
const BUCKETS_MS = [5, 10, 25, 50, 100, 200, 500, 1000, 2500, 5000, Infinity];
const bucketCounts = new Map<string, number[]>(); // key → per-bucket counts
const durationSum = new Map<string, number>();     // key → sum of durations
const durationCount = new Map<string, number>();   // key → total observations

// ---------------------------------------------------------------------------
// Public API — called from TelemetryMiddleware
// ---------------------------------------------------------------------------

/**
 * Record a completed HTTP request.
 * @param method  HTTP verb (GET, POST, …)
 * @param path    Normalised route path (avoid high-cardinality raw URLs)
 * @param status  HTTP status code
 * @param durationMs  Wall-clock duration in milliseconds
 */
export function recordRequest(
  method: string,
  path: string,
  status: number,
  durationMs: number,
): void {
  const statusClass = `${Math.floor(status / 100)}xx`;
  const countKey = `${method}|${path}|${statusClass}`;

  requestCounts.set(countKey, (requestCounts.get(countKey) ?? 0) + 1);

  const histKey = `${method}|${path}`;
  if (!bucketCounts.has(histKey)) {
    bucketCounts.set(histKey, new Array<number>(BUCKETS_MS.length).fill(0));
  }
  const counts = bucketCounts.get(histKey)!;
  for (let i = 0; i < BUCKETS_MS.length; i++) {
    if (durationMs <= BUCKETS_MS[i]) counts[i]++;
  }
  durationSum.set(histKey, (durationSum.get(histKey) ?? 0) + durationMs);
  durationCount.set(histKey, (durationCount.get(histKey) ?? 0) + 1);
}

// ---------------------------------------------------------------------------
// Prometheus text exposition
// ---------------------------------------------------------------------------

export function getMetricsText(): string {
  const lines: string[] = [];

  // --- service info gauge ---
  lines.push('# HELP iso27001_info Service information');
  lines.push('# TYPE iso27001_info gauge');
  lines.push(`iso27001_info{service="iso27001-nestjs",version="1.0.0"} 1`);
  lines.push('');

  // --- http_requests_total counter ---
  lines.push('# HELP http_requests_total Total HTTP requests by method, path, and status class');
  lines.push('# TYPE http_requests_total counter');
  for (const [key, count] of requestCounts) {
    const [method, path, statusClass] = key.split('|');
    lines.push(`http_requests_total{method="${method}",path="${path}",status_class="${statusClass}"} ${count}`);
  }
  lines.push('');

  // --- http_request_duration_ms histogram ---
  lines.push('# HELP http_request_duration_ms HTTP request latency in milliseconds');
  lines.push('# TYPE http_request_duration_ms histogram');
  for (const [key, counts] of bucketCounts) {
    const [method, path] = key.split('|');
    const labels = `method="${method}",path="${path}"`;
    for (let i = 0; i < BUCKETS_MS.length; i++) {
      const le = BUCKETS_MS[i] === Infinity ? '+Inf' : String(BUCKETS_MS[i]);
      lines.push(`http_request_duration_ms_bucket{${labels},le="${le}"} ${counts[i]}`);
    }
    lines.push(`http_request_duration_ms_sum{${labels}} ${(durationSum.get(key) ?? 0).toFixed(3)}`);
    lines.push(`http_request_duration_ms_count{${labels}} ${durationCount.get(key) ?? 0}`);
  }
  lines.push('');

  return lines.join('\n');
}
