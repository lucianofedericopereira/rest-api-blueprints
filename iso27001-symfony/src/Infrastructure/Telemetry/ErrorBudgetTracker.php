<?php

declare(strict_types=1);

namespace App\Infrastructure\Telemetry;

/**
 * A.17: Error budget tracker — Redis-backed with in-process fallback.
 *
 * Counts 5xx responses against a configured SLA target. When the budget is
 * exhausted the system should freeze risky deployments and trigger alerts.
 *
 * SLA mapping (99.9% = 43.8 min/month budget):
 *   99.9%  →  43.8 min downtime budget per month
 *   99.95% →  21.9 min downtime budget per month
 *   99.99% →   4.4 min downtime budget per month
 *
 * Storage strategy ("if available, use it"):
 *   1. Redis (ext-redis)  — atomic INCR, survives worker restarts, cross-process accurate.
 *   2. In-process counters — zero-dependency fallback; accurate within a single worker.
 *
 * To enable Redis: ensure ext-redis is installed and set REDIS_URL in the environment.
 *   e.g. REDIS_URL=redis://127.0.0.1:6379
 */
final class ErrorBudgetTracker
{
    private const KEY_TOTAL  = 'error_budget:%s:total';
    private const KEY_FAILED = 'error_budget:%s:failed';

    /** In-process fallback counters */
    private int $localTotal  = 0;
    private int $localFailed = 0;

    private readonly ?\Redis $redis;
    private readonly string  $keyPrefix;

    public function __construct(
        private readonly float $slaTarget = 0.999,
        string $keyPrefix = 'app',
        ?\Redis $redis = null,
    ) {
        if ($slaTarget <= 0.0 || $slaTarget >= 1.0) {
            throw new \InvalidArgumentException('slaTarget must be between 0 and 1 exclusive');
        }

        $this->keyPrefix = $keyPrefix;
        $this->redis     = $redis ?? $this->detectRedis();
    }

    /** Record a completed request. Any 5xx status counts as a budget deduction. */
    public function record(int $statusCode): void
    {
        if ($this->redis !== null) {
            try {
                $this->redis->incr(sprintf(self::KEY_TOTAL, $this->keyPrefix));
                if ($statusCode >= 500) {
                    $this->redis->incr(sprintf(self::KEY_FAILED, $this->keyPrefix));
                }
                return;
            } catch (\Throwable) {
                // Fall through to in-process
            }
        }

        ++$this->localTotal;
        if ($statusCode >= 500) {
            ++$this->localFailed;
        }
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        [$total, $failed, $backend] = $this->readCounters();

        if ($total === 0) {
            return [
                'sla_target'            => $this->slaTarget,
                'total_requests'        => 0,
                'failed_requests'       => 0,
                'observed_availability' => 1.0,
                'budget_consumed_pct'   => 0.0,
                'budget_exhausted'      => false,
                'backend'               => $backend,
            ];
        }

        $availability     = ($total - $failed) / $total;
        $allowedErrorRate = 1.0 - $this->slaTarget;
        $actualErrorRate  = $failed / $total;

        $budgetConsumedPct = $allowedErrorRate > 0.0
            ? min(($actualErrorRate / $allowedErrorRate) * 100.0, 100.0)
            : ($failed > 0 ? 100.0 : 0.0);

        return [
            'sla_target'            => $this->slaTarget,
            'total_requests'        => $total,
            'failed_requests'       => $failed,
            'observed_availability' => round($availability, 6),
            'budget_consumed_pct'   => round($budgetConsumedPct, 2),
            'budget_exhausted'      => $budgetConsumedPct >= 100.0,
            'backend'               => $backend,
        ];
    }

    public function reset(): void
    {
        if ($this->redis !== null) {
            try {
                $this->redis->del(
                    sprintf(self::KEY_TOTAL, $this->keyPrefix),
                    sprintf(self::KEY_FAILED, $this->keyPrefix),
                );
                return;
            } catch (\Throwable) {
                // Fall through to in-process
            }
        }

        $this->localTotal  = 0;
        $this->localFailed = 0;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /** @return array{int, int, string} */
    private function readCounters(): array
    {
        if ($this->redis !== null) {
            try {
                $total  = (int) ($this->redis->get(sprintf(self::KEY_TOTAL, $this->keyPrefix)) ?: 0);
                $failed = (int) ($this->redis->get(sprintf(self::KEY_FAILED, $this->keyPrefix)) ?: 0);
                return [$total, $failed, 'redis'];
            } catch (\Throwable) {
                // Fall through to in-process
            }
        }

        return [$this->localTotal, $this->localFailed, 'in-process'];
    }

    /**
     * Auto-detect Redis from the REDIS_URL environment variable.
     * Returns null if ext-redis is missing, the env var is unset, or the
     * connection attempt fails — no exception is ever propagated.
     */
    private function detectRedis(): ?\Redis
    {
        if (!extension_loaded('redis')) {
            return null;
        }

        $url = $_ENV['REDIS_URL'] ?? getenv('REDIS_URL') ?: null;
        if ($url === null) {
            return null;
        }

        try {
            $parsed = parse_url($url);
            $host   = $parsed['host'] ?? '127.0.0.1';
            $port   = $parsed['port'] ?? 6379;

            $redis = new \Redis();
            $redis->connect((string) $host, (int) $port, 0.5);

            if (isset($parsed['pass']) && $parsed['pass'] !== '') {
                $redis->auth($parsed['pass']);
            }

            return $redis;
        } catch (\Throwable) {
            return null;
        }
    }
}
