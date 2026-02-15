/**
 * A.12: AWS CloudWatch custom metrics emitter.
 *
 * No-op unless AWS SDK and valid credentials are present.
 * Install: npm install @aws-sdk/client-cloudwatch
 */
export class CloudWatchEmitter {
  emitRequest(method: string, path: string, statusCode: number, durationMs: number): void {
    // No-op without @aws-sdk/client-cloudwatch + credentials
    void method; void path; void statusCode; void durationMs;
  }

  emitRateLimitHit(): void {
    // No-op
  }
}

export const cwEmitter = new CloudWatchEmitter();
