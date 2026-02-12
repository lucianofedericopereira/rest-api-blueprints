<?php

declare(strict_types=1);

namespace App\Infrastructure\Telemetry;

use Illuminate\Support\Facades\Redis;

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
 *   1. Laravel Redis facade (predis / phpredis) — atomic INCR, survives worker
 *      restarts, cross-process accurate.  Requires REDIS_HOST in .env and the
 *      illuminate/redis package (bundled with laravel/framework).
 *   2. In-process counters — zero-dependency fallback; accurate within a single
 *      PHP-FPM worker.
 */
final class ErrorBudgetTracker
{
    private const KEY_TOTAL  = 'error_budget:%s:total';
    private const KEY_FAILED = 'error_budget:%s:failed';

    /** In-process fallback counters */
    private int $localTotal  = 0;
    private int $localFailed = 0;

    private readonly bool   $redisAvailable;
    private readonly string $keyPrefix;

    public function __construct(
        private readonly float $slaTarget = 0.999,
        string $keyPrefix = 'app',
    ) {
        if ($slaTarget <= 0.0 || $slaTarget >= 1.0) {
            throw new \InvalidArgumentException('slaTarget must be between 0 and 1 exclusive');
        }

        $this->keyPrefix      = $keyPrefix;
        $this->redisAvailable = $this->detectRedis();
    }

    /** Record a completed request. Any 5xx status counts as a budget deduction. */
    public function record(int $statusCode): void
    {
        if ($this->redisAvailable) {
            try {
                $conn = Redis::connection();
                $conn->incr(sprintf(self::KEY_TOTAL, $this->keyPrefix));
                if ($statusCode >= 500) {
                    $conn->incr(sprintf(self::KEY_FAILED, $this->keyPrefix));
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
        if ($this->redisAvailable) {
            try {
                $conn = Redis::connection();
                $conn->del(
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
        if ($this->redisAvailable) {
            try {
                $conn   = Redis::connection();
                $total  = (int) ($conn->get(sprintf(self::KEY_TOTAL, $this->keyPrefix)) ?? 0);
                $failed = (int) ($conn->get(sprintf(self::KEY_FAILED, $this->keyPrefix)) ?? 0);
                return [$total, $failed, 'redis'];
            } catch (\Throwable) {
                // Fall through to in-process
            }
        }

        return [$this->localTotal, $this->localFailed, 'in-process'];
    }

    /**
     * Check whether the Laravel Redis connection is usable.
     * Returns false if the Redis facade is absent, not configured, or
     * the ping fails — no exception is ever propagated.
     */
    private function detectRedis(): bool
    {
        if (!class_exists(Redis::class)) {
            return false;
        }

        // Require REDIS_HOST to be explicitly configured
        if (empty(config('database.redis.default.host'))) {
            return false;
        }

        try {
            Redis::connection()->ping();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
