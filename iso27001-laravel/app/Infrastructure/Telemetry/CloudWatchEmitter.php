<?php

declare(strict_types=1);

namespace App\Infrastructure\Telemetry;

use Illuminate\Support\Facades\Log;

/**
 * AWS CloudWatch custom-metrics emitter.
 *
 * Calls PutMetricData via the AWS SDK for PHP (aws/aws-sdk-php).
 * When the SDK is not installed or credentials are absent this class
 * degrades gracefully to a no-op so dev/test environments are unaffected.
 *
 * Installation (optional):
 *   composer require aws/aws-sdk-php
 *
 * Required environment variables (or use an IAM role — preferred):
 *   AWS_DEFAULT_REGION=eu-west-1
 *   AWS_CLOUDWATCH_NAMESPACE=ISO27001/API
 */
final class CloudWatchEmitter
{
    /** @var object|null AWS CloudWatch client */
    private ?object $client = null;

    private string $namespace;
    private string $service;
    private string $environment;

    public function __construct()
    {
        $this->namespace   = (string) config('services.aws.cloudwatch_namespace', 'ISO27001/API');
        $this->service     = (string) config('app.name', 'iso27001-api');
        $this->environment = (string) config('app.env', 'production');
        $this->client      = $this->buildClient();
    }

    public function emitRequest(string $method, string $path, int $statusCode, float $durationMs): void
    {
        $this->put('RequestCount', 1, 'Count', [
            ['Name' => 'Method',     'Value' => $method],
            ['Name' => 'StatusCode', 'Value' => (string) $statusCode],
        ]);
        $this->put('RequestLatency', $durationMs, 'Milliseconds', [
            ['Name' => 'Path', 'Value' => $path],
        ]);
        if ($statusCode >= 500) {
            $this->put('ServerErrors', 1, 'Count');
        }
    }

    public function emitAuthFailure(): void
    {
        $this->put('AuthFailures', 1, 'Count');
    }

    public function emitRateLimitHit(): void
    {
        $this->put('RateLimitHits', 1, 'Count');
    }

    public function emitErrorBudget(float $consumedPct): void
    {
        $this->put('ErrorBudgetConsumedPct', $consumedPct, 'Percent');
    }

    public function emitQualityScore(float $compositeScore): void
    {
        $this->put('QualityScore', $compositeScore * 100, 'Percent');
    }

    // ── internals ────────────────────────────────────────────────────────────

    /** @param list<array{Name: string, Value: string}> $extraDimensions */
    private function put(string $name, float $value, string $unit, array $extraDimensions = []): void
    {
        if ($this->client === null) {
            return;
        }

        $dimensions = array_merge(
            [
                ['Name' => 'Service',     'Value' => $this->service],
                ['Name' => 'Environment', 'Value' => $this->environment],
            ],
            $extraDimensions,
        );

        try {
            /** @phpstan-ignore-next-line */
            $this->client->putMetricData([
                'Namespace'  => $this->namespace,
                'MetricData' => [[
                    'MetricName' => $name,
                    'Dimensions' => $dimensions,
                    'Timestamp'  => new \DateTimeImmutable(),
                    'Value'      => $value,
                    'Unit'       => $unit,
                ]],
            ]);
        } catch (\Throwable $e) {
            // Never let telemetry failure crash the application
            Log::warning('CloudWatch emit failed', ['error' => $e->getMessage()]);
        }
    }

    private function buildClient(): ?object
    {
        if (!class_exists('Aws\CloudWatch\CloudWatchClient')) {
            return null;
        }

        $region      = (string) config('services.aws.region', 'eu-west-1');
        $clientClass = 'Aws\CloudWatch\CloudWatchClient';

        try {
            /** @phpstan-ignore-next-line */
            return new $clientClass(['region' => $region, 'version' => 'latest']);
        } catch (\Throwable $e) {
            Log::warning('Could not build CloudWatch client', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
