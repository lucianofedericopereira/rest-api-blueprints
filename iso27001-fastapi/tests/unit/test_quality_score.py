"""Unit tests for QualityScoreCalculator."""
import pytest
from app.infrastructure.quality_score import QualityScoreCalculator, QualityScore, PRODUCTION_GATE


def _perfect_score() -> QualityScore:
    calc = QualityScoreCalculator(sla_latency_p95_ms=0.0, target_latency_ms=200.0)
    return calc.calculate(
        auth_checks_passed=100,
        auth_checks_total=100,
        audit_events_recorded=100,
        audit_events_expected=100,
        availability=1.0,
        logs_with_correlation_id=100,
        total_logs=100,
    )


def test_perfect_score_is_1():
    score = _perfect_score()
    assert score.composite() == pytest.approx(1.0, abs=1e-6)


def test_perfect_score_passes_gate():
    assert _perfect_score().passes_gate()


def test_weights_sum_to_095():
    # weights: 0.40 + 0.20 + 0.15 + 0.15 + 0.05 = 0.95
    # (5% reserved for future "gap" pillar)
    total = 0.40 + 0.20 + 0.15 + 0.15 + 0.05
    assert total == pytest.approx(0.95)


def test_zero_security_pulls_composite_below_gate():
    calc = QualityScoreCalculator(sla_latency_p95_ms=0.0, target_latency_ms=200.0)
    score = calc.calculate(
        auth_checks_passed=0,   # all auth failed
        auth_checks_total=100,
        audit_events_recorded=100,
        audit_events_expected=100,
        availability=1.0,
        logs_with_correlation_id=100,
        total_logs=100,
    )
    # security=0 * 0.40 + others * 0.55 = 0.55 → below 0.70 gate
    assert score.composite() < PRODUCTION_GATE
    assert not score.passes_gate()


def test_no_data_defaults_to_1():
    """When no denominator data exists, assume perfect (avoid false alarm at startup)."""
    calc = QualityScoreCalculator(sla_latency_p95_ms=0.0, target_latency_ms=200.0)
    score = calc.calculate(
        auth_checks_passed=0,
        auth_checks_total=0,   # no data
        audit_events_recorded=0,
        audit_events_expected=0,
        availability=1.0,
        logs_with_correlation_id=0,
        total_logs=0,
    )
    assert score.security == 1.0
    assert score.data_integrity == 1.0
    assert score.auditability == 1.0


def test_to_dict_shape():
    score = _perfect_score()
    d = score.to_dict()
    assert "composite" in d
    assert "passes_gate" in d
    assert "pillars" in d
    assert set(d["pillars"]) == {"security", "data_integrity", "reliability", "auditability", "performance"}
