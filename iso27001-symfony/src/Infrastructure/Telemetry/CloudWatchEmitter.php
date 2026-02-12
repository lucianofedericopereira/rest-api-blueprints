<?php

declare(strict_types=1);

namespace App\Infrastructure\Telemetry;

use Psr\Log\LoggerInterface;

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
    private readonly string $namespace;
    private readonly string $environment;
    private readonly string $service;
    /** @var object|null AWS CloudWatch client */
    private ?object $client = null;

    public function __construct(
        private readonly LoggerInterface $logger,
        string $serviceName,
        string $environment,
        string $namespace = 'ISO27001/API',
    ) {
        $this->namespace   = $namespace;
        $this->environment = $environment;
        $this->service     = $serviceName;
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
            $this->logger->warning('CloudWatch emit failed', ['error' => $e->getMessage()]);
        }
    }

    private function buildClient(): ?object
    {
        $fqcn = 'Aws\CloudWatch\CloudWatchClient';

        if (!class_exists($fqcn)) {
            return null;
        }

        $region = (string) ($_SERVER['AWS_DEFAULT_REGION'] ?? 'eu-west-1');

        try {
            return new $fqcn(['region' => $region, 'version' => 'latest']);
        } catch (\Throwable $e) {
            $this->logger->warning('Could not build CloudWatch client', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
