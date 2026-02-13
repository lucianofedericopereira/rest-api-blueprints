<?php

declare(strict_types=1);

namespace App\Infrastructure\Telemetry;

use App\Domain\Shared\Contracts\MetricsCollectorInterface;
use Illuminate\Support\Facades\Log;

/**
 * A.12: Lightweight metrics collector.
 * In production, replace with a Prometheus push-gateway client,
 * StatsD emitter, or Datadog DogStatsD client.
 */
final class MetricsCollector implements MetricsCollectorInterface
{
    /** @var array<string, int> */
    private array $counters = [];

    /** @param array<string, string> $tags */
    public function increment(string $metric, array $tags = [], int $value = 1): void
    {
        $key = $this->key($metric, $tags);
        $this->counters[$key] = ($this->counters[$key] ?? 0) + $value;

        Log::debug('metric.counter', [
            'metric' => $metric,
            'tags'   => $tags,
            'value'  => $this->counters[$key],
        ]);
    }

    /** @param array<string, string> $tags */
    public function timing(string $metric, float $durationMs, array $tags = []): void
    {
        Log::debug('metric.timing', [
            'metric'      => $metric,
            'tags'        => $tags,
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
