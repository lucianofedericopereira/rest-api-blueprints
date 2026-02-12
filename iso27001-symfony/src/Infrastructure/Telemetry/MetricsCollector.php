<?php

declare(strict_types=1);

namespace App\Infrastructure\Telemetry;

use Psr\Log\LoggerInterface;

/**
 * A.12: Lightweight metrics collector.
 * In production, replace with a Prometheus push-gateway client or StatsD emitter.
 */
final class MetricsCollector
{
    /** @var array<string, int> */
    private array $counters = [];

    /** @var array<string, list<float>> @phpstan-ignore property.onlyWritten */
    private array $histograms = [];

    public function __construct(private readonly LoggerInterface $logger) {}

    /** @param array<string, string> $tags */
    public function increment(string $metric, array $tags = [], int $value = 1): void
    {
        $key = $this->key($metric, $tags);
        $this->counters[$key] = ($this->counters[$key] ?? 0) + $value;

        $this->logger->debug('metric.counter', [
            'metric' => $metric,
            'tags' => $tags,
            'value' => $this->counters[$key],
        ]);
    }

    /** @param array<string, string> $tags */
    public function timing(string $metric, float $durationMs, array $tags = []): void
    {
        $key = $this->key($metric, $tags);
        $this->histograms[$key][] = $durationMs;

        $this->logger->debug('metric.timing', [
            'metric' => $metric,
            'tags' => $tags,
            'duration_ms' => $durationMs,
        ]);
    }

    /** @param array<string, string> $tags */
    private function key(string $metric, array $tags): string
    {
        ksort($tags);
        $tagStr = implode(',', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($tags), $tags));
        return $tagStr ? "{$metric}{{$tagStr}}" : $metric;
    }
}
