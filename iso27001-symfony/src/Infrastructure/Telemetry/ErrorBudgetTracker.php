<?php

declare(strict_types=1);

namespace App\Infrastructure\Telemetry;

/**
 * A.17: Thread-safe* error budget tracker.
 *
 * Counts 5xx responses against a configured SLA target. When the budget is
 * exhausted the system should freeze risky deployments and trigger alerts.
 *
 * SLA mapping (99.9% = 43.8 min/month budget):
 *   99.9%  →  43.8 min downtime budget per month
 *   99.95% →  21.9 min downtime budget per month
 *   99.99% →   4.4 min downtime budget per month
 *
 * * PHP-FPM is single-threaded per worker; counters are in-process only.
 *   For multi-process durability, back the counters with Redis or APCu.
 */
final class ErrorBudgetTracker
{
    private int $total = 0;
    private int $failed = 0;

    public function __construct(private readonly float $slaTarget = 0.999)
    {
        if ($slaTarget <= 0.0 || $slaTarget >= 1.0) {
            throw new \InvalidArgumentException('slaTarget must be between 0 and 1 exclusive');
        }
    }

    /** Record a completed request. Any 5xx status counts as a budget deduction. */
    public function record(int $statusCode): void
    {
        ++$this->total;
        if ($statusCode >= 500) {
            ++$this->failed;
        }
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        if ($this->total === 0) {
            return [
                'sla_target'            => $this->slaTarget,
                'total_requests'        => 0,
                'failed_requests'       => 0,
                'observed_availability' => 1.0,
                'budget_consumed_pct'   => 0.0,
                'budget_exhausted'      => false,
            ];
        }

        $availability     = ($this->total - $this->failed) / $this->total;
        $allowedErrorRate = 1.0 - $this->slaTarget;
        $actualErrorRate  = $this->failed / $this->total;

        $budgetConsumedPct = $allowedErrorRate > 0.0
            ? min(($actualErrorRate / $allowedErrorRate) * 100.0, 100.0)
            : ($this->failed > 0 ? 100.0 : 0.0);

        return [
            'sla_target'            => $this->slaTarget,
            'total_requests'        => $this->total,
            'failed_requests'       => $this->failed,
            'observed_availability' => round($availability, 6),
            'budget_consumed_pct'   => round($budgetConsumedPct, 2),
            'budget_exhausted'      => $budgetConsumedPct >= 100.0,
        ];
    }

    public function reset(): void
    {
        $this->total  = 0;
        $this->failed = 0;
    }
}
