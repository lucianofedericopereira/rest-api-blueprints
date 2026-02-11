"""
A.17: Error Budget Tracker — SLA enforcement.

Tracks availability against a configured error budget. When the budget is
exhausted the system should freeze risky deployments and trigger alerts.

SLA mapping (99.9% = 43.8 min/month budget):
  - 99.9%  →  43.8 min downtime budget per month
  - 99.95% →  21.9 min downtime budget per month
  - 99.99% →   4.4 min downtime budget per month
"""
from __future__ import annotations

import time
from dataclasses import dataclass, field
from threading import Lock
from typing import Optional


@dataclass
class ErrorBudgetSnapshot:
    sla_target: float          # e.g. 0.999 for 99.9%
    total_requests: int
    failed_requests: int
    observed_availability: float
    budget_consumed_pct: float  # 0–100; >100 means budget exhausted
    budget_exhausted: bool


class ErrorBudgetTracker:
    """
    Thread-safe sliding-window error budget tracker.

    Counts 5xx responses as "errors". 4xx responses are NOT counted
    (they represent valid client errors, not service failures).

    Usage:
        tracker = ErrorBudgetTracker(sla_target=0.999)
        tracker.record(status_code=200)
        tracker.record(status_code=500)
        snapshot = tracker.snapshot()
    """

    def __init__(self, sla_target: float = 0.999) -> None:
        if not (0.0 < sla_target < 1.0):
            raise ValueError("sla_target must be between 0 and 1 exclusive")
        self._sla_target = sla_target
        self._total: int = 0
        self._failed: int = 0
        self._lock = Lock()

    def record(self, status_code: int) -> None:
        """Record a completed request. Any 5xx counts as a budget deduction."""
        with self._lock:
            self._total += 1
            if status_code >= 500:
                self._failed += 1

    def snapshot(self) -> ErrorBudgetSnapshot:
        """Return current error budget state."""
        with self._lock:
            total = self._total
            failed = self._failed

        if total == 0:
            return ErrorBudgetSnapshot(
                sla_target=self._sla_target,
                total_requests=0,
                failed_requests=0,
                observed_availability=1.0,
                budget_consumed_pct=0.0,
                budget_exhausted=False,
            )

        availability = (total - failed) / total
        allowed_error_rate = 1.0 - self._sla_target
        actual_error_rate = failed / total

        # budget_consumed = how much of the allowed error budget has been used
        if allowed_error_rate == 0:
            budget_consumed_pct = 100.0 if failed > 0 else 0.0
        else:
            budget_consumed_pct = min((actual_error_rate / allowed_error_rate) * 100.0, 100.0)

        return ErrorBudgetSnapshot(
            sla_target=self._sla_target,
            total_requests=total,
            failed_requests=failed,
            observed_availability=round(availability, 6),
            budget_consumed_pct=round(budget_consumed_pct, 2),
            budget_exhausted=budget_consumed_pct >= 100.0,
        )

    def reset(self) -> None:
        """Reset counters (e.g. at the start of a new SLA measurement period)."""
        with self._lock:
            self._total = 0
            self._failed = 0


# Module-level singleton — shared across all middleware/handlers
error_budget = ErrorBudgetTracker(sla_target=0.999)
