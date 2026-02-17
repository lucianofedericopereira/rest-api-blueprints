/**
 * A.12: AWS CloudWatch custom metrics emitter.
 *
 * Emits custom metrics to CloudWatch when @aws-sdk/client-cloudwatch is
 * installed and AWS credentials are present (IAM role or env vars).
 * All methods are no-ops otherwise — the application runs normally without it.
 *
 * Install (optional):
 *   npm install @aws-sdk/client-cloudwatch
 *
 * Env vars:
 *   AWS_DEFAULT_REGION          (default: eu-west-1)
 *   AWS_CLOUDWATCH_NAMESPACE    (default: ISO27001/API)
 *   APP_NAME, APP_ENV           resolved from process.env
 */

const CW_NAMESPACE = process.env.AWS_CLOUDWATCH_NAMESPACE ?? 'ISO27001/API';
const AWS_REGION   = process.env.AWS_DEFAULT_REGION ?? 'eu-west-1';

type CwClient = { send: (cmd: unknown) => Promise<void> };
type CwModule = {
  CloudWatchClient: new (cfg: { region: string }) => CwClient;
  PutMetricDataCommand: new (input: {
    Namespace: string;
    MetricData: Array<{
      MetricName: string;
      Dimensions: Array<{ Name: string; Value: string }>;
      Value: number;
      Unit: string;
      Timestamp: Date;
    }>;
  }) => unknown;
};

/** Dynamically require CloudWatchClient — returns null when SDK is absent. */
function buildClient(): CwClient | null {
  try {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    const { CloudWatchClient } = require('@aws-sdk/client-cloudwatch') as CwModule;
    return new CloudWatchClient({ region: AWS_REGION });
  } catch {
    return null;
  }
}

async function putMetric(
  client: CwClient | null,
  name: string,
  value: number,
  unit: string,
  extraDimensions: Array<{ Name: string; Value: string }> = [],
): Promise<void> {
  if (!client) return;
  try {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    const { PutMetricDataCommand } = require('@aws-sdk/client-cloudwatch') as CwModule;
    const dimensions = [
      { Name: 'Service',     Value: process.env.APP_NAME ?? 'iso27001-api' },
      { Name: 'Environment', Value: process.env.APP_ENV  ?? 'production'   },
      ...extraDimensions,
    ];
    await (client as { send: (cmd: unknown) => Promise<void> }).send(
      new PutMetricDataCommand({
        Namespace:  CW_NAMESPACE,
        MetricData: [{ MetricName: name, Dimensions: dimensions, Value: value, Unit: unit, Timestamp: new Date() }],
      }),
    );
  } catch {
    // Never let telemetry failure crash the application
  }
}

export class CloudWatchEmitter {
  private readonly client: CwClient | null;

  constructor() {
    this.client = buildClient();
  }

  /** A.12: Record HTTP request count and latency. */
  emitRequest(method: string, path: string, statusCode: number, durationMs: number): void {
    void putMetric(this.client, 'RequestCount',   1,          'Count',        [{ Name: 'Method', Value: method }, { Name: 'StatusCode', Value: String(statusCode) }]);
    void putMetric(this.client, 'RequestLatency', durationMs, 'Milliseconds', [{ Name: 'Path',   Value: path   }]);
    if (statusCode >= 500) {
      void putMetric(this.client, 'ServerErrors', 1, 'Count');
    }
  }

  /** A.9: Track authentication failures for anomaly detection. */
  emitAuthFailure(): void {
    void putMetric(this.client, 'AuthFailures', 1, 'Count');
  }

  /** A.17: Track rate-limit events. */
  emitRateLimitHit(): void {
    void putMetric(this.client, 'RateLimitHits', 1, 'Count');
  }

  /** A.17: Publish error budget consumption as a gauge. */
  emitErrorBudget(budgetConsumedPct: number): void {
    void putMetric(this.client, 'ErrorBudgetConsumedPct', budgetConsumedPct, 'Percent');
  }

  /** Publish the composite quality score (0–1 mapped to 0–100). */
  emitQualityScore(compositeScore: number): void {
    void putMetric(this.client, 'QualityScore', compositeScore * 100, 'Percent');
  }
}

export const cwEmitter = new CloudWatchEmitter();

/**
 * A.12: AWS X-Ray trace header propagation.
 *
 * Reads the X-Amzn-Trace-Id header from incoming requests and propagates it
 * through logs and outgoing responses so distributed traces are linked in the
 * X-Ray console.
 *
 * Works without any SDK — treats the header as a pass-through correlation ID.
 * Full segment tracing (begin_segment / end_segment) is opt-in via
 * npm install aws-xray-sdk-core.
 */
export class XRayTracer {
  static readonly HEADER = 'x-amzn-trace-id';

  /**
   * Extract the X-Ray trace ID from incoming request headers.
   * Header format: Root=1-xxxxxxxx-xxxxxxxxxxxxxxxxxxxx;Parent=xxxx;Sampled=1
   * Returns null when the header is absent (non-AWS environments).
   */
  extractTraceId(headers: Record<string, string | string[] | undefined>): string | null {
    const raw = headers[XRayTracer.HEADER] ?? headers['X-Amzn-Trace-Id'];
    if (!raw) return null;
    const value = Array.isArray(raw) ? raw[0] : raw;
    return value ?? null;
  }
}

export const xray = new XRayTracer();
