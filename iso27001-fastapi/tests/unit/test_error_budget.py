"""Unit tests for ErrorBudgetTracker."""
import pytest
from app.infrastructure.error_budget import ErrorBudgetTracker


def test_no_requests_returns_zero_consumed():
    tracker = ErrorBudgetTracker(sla_target=0.999)
    snap = tracker.snapshot()
    assert snap.total_requests == 0
    assert snap.budget_consumed_pct == 0.0
    assert not snap.budget_exhausted


def test_all_success_consumes_no_budget():
    tracker = ErrorBudgetTracker(sla_target=0.999)
    for _ in range(1000):
        tracker.record(200)
    snap = tracker.snapshot()
    assert snap.failed_requests == 0
    assert snap.budget_consumed_pct == 0.0
    assert snap.observed_availability == 1.0


def test_5xx_consumes_budget():
    tracker = ErrorBudgetTracker(sla_target=0.999)
    for _ in range(999):
        tracker.record(200)
    tracker.record(500)   # 0.1% error rate == exactly the budget limit
    snap = tracker.snapshot()
    assert snap.failed_requests == 1
    assert snap.budget_consumed_pct == 100.0
    assert snap.budget_exhausted is True


def test_4xx_does_not_consume_budget():
    tracker = ErrorBudgetTracker(sla_target=0.999)
    for _ in range(100):
        tracker.record(404)
    snap = tracker.snapshot()
    assert snap.failed_requests == 0
    assert snap.budget_consumed_pct == 0.0


def test_reset_clears_counters():
    tracker = ErrorBudgetTracker(sla_target=0.999)
    tracker.record(500)
    tracker.reset()
    snap = tracker.snapshot()
    assert snap.total_requests == 0


def test_invalid_sla_target_raises():
    with pytest.raises(ValueError):
        ErrorBudgetTracker(sla_target=1.0)
    with pytest.raises(ValueError):
        ErrorBudgetTracker(sla_target=0.0)
