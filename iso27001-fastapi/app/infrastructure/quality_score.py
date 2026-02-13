"""
Risk-weighted Quality Score Calculator.

Composite score derived from five pillars aligned to ISO 27001 domains:

  Pillar           Weight   ISO 27001 Annex
  ─────────────────────────────────────────
  Security          40%     A.9, A.10
  Data Integrity    20%     A.12
  Reliability       15%     A.17
  Auditability      15%     A.12
  Performance        5%     A.17
  (Bonus gap)        5%     reserved

Score of 1.0 = perfect compliance; 0.0 = complete failure.
A score below 0.70 should block production deployments.
"""
from __future__ import annotations

from dataclasses import dataclass


PRODUCTION_GATE = 0.70   # minimum acceptable score


@dataclass(frozen=True)
class SloAlert:
    """
    A.17: SLO breach signals for alerting pipelines.

    Fields are set to True when the corresponding SLO threshold is exceeded.
    All four signals are independent — any True value should trigger an alert.
    """
    p95_latency_breached: bool   # observed P95 > SLO_P95_LATENCY_MS
    p99_latency_breached: bool   # observed P99 > SLO_P99_LATENCY_MS
    error_rate_breached: bool    # 5xx error rate > SLO_ERROR_RATE_PCT
    client_error_spike: bool     # 4xx rate > CLIENT_ERROR_SPIKE_PCT (abuse signal)

    def any_breach(self) -> bool:
        """True if at least one SLO is currently violated."""
        return (
            self.p95_latency_breached
            or self.p99_latency_breached
            or self.error_rate_breached
            or self.client_error_spike
        )

    def to_dict(self) -> dict[str, bool]:
        return {
            "any_breach": self.any_breach(),
            "p95_latency_breached": self.p95_latency_breached,
            "p99_latency_breached": self.p99_latency_breached,
            "error_rate_breached": self.error_rate_breached,
            "client_error_spike": self.client_error_spike,
        }


@dataclass(frozen=True)
class QualityScore:
    security: float          # 0–1  (JWT validity, RBAC enforcement, encryption)
    data_integrity: float    # 0–1  (audit log completeness, event dispatch success)
    reliability: float       # 0–1  (1 - error_rate, derived from ErrorBudgetTracker)
    auditability: float      # 0–1  (structured log coverage, correlation ID presence)
    performance: float       # 0–1  (P95 latency vs SLA threshold)

    def composite(self) -> float:
        """
        Weighted composite score, normalized to [0.0, 1.0].
        Weights sum to 0.95 (5% reserved for a future gap pillar);
        dividing by 0.95 maps a perfect score to exactly 1.0.
        """
        _WEIGHT_SUM = 0.95
        raw = (
            self.security        * 0.40
            + self.data_integrity  * 0.20
            + self.reliability     * 0.15
            + self.auditability    * 0.15
            + self.performance     * 0.05
        )
        return raw / _WEIGHT_SUM

    def passes_gate(self) -> bool:
        """True if this score meets the production deployment gate."""
        return self.composite() >= PRODUCTION_GATE

    def to_dict(self) -> dict[str, object]:
        c = self.composite()
        return {
            "composite": round(c, 4),
            "passes_gate": self.passes_gate(),
            "production_gate_threshold": PRODUCTION_GATE,
            "pillars": {
                "security":       {"score": self.security,       "weight": 0.40},
                "data_integrity": {"score": self.data_integrity, "weight": 0.20},
                "reliability":    {"score": self.reliability,    "weight": 0.15},
                "auditability":   {"score": self.auditability,   "weight": 0.15},
                "performance":    {"score": self.performance,    "weight": 0.05},
            },
        }


class QualityScoreCalculator:
    """
    Assembles a QualityScore and SloAlert from live runtime signals.

    Inject the dependencies it needs (error budget, latency histogram, etc.)
    rather than reaching for global state directly — makes it unit-testable.
    """

    # A.17: SLO thresholds — defined once, referenced everywhere.
    SLO_P95_LATENCY_MS: float = 200.0    # alert if P95 exceeds 200 ms
    SLO_P99_LATENCY_MS: float = 500.0    # alert if P99 exceeds 500 ms
    SLO_ERROR_RATE_PCT: float = 0.1      # alert if 5xx rate (%) exceeds 0.1% (= 99.9% SLA)
    CLIENT_ERROR_SPIKE_PCT: float = 5.0  # alert if 4xx rate (%) exceeds 5% (abuse signal)

    def __init__(
        self,
        *,
        sla_latency_p95_ms: float = 200.0,
        target_latency_ms: float = 500.0,
        sla_latency_p99_ms: float = 0.0,
    ) -> None:
        """
        Args:
            sla_latency_p95_ms: Observed P95 latency in milliseconds (from metrics).
            target_latency_ms:  Acceptable P95 latency ceiling from SLA.
            sla_latency_p99_ms: Observed P99 latency in milliseconds (from metrics).
        """
        self._sla_latency_p95_ms = sla_latency_p95_ms
        self._target_latency_ms = target_latency_ms
        self._sla_latency_p99_ms = sla_latency_p99_ms

    def calculate(
        self,
        *,
        auth_checks_passed: int,
        auth_checks_total: int,
        audit_events_recorded: int,
        audit_events_expected: int,
        availability: float,
        logs_with_correlation_id: int,
        total_logs: int,
    ) -> QualityScore:
        """
        Compute each pillar score and return a QualityScore.

        All inputs must be non-negative integers or floats in [0, 1].
        """
        security = self._ratio(auth_checks_passed, auth_checks_total)
        data_integrity = self._ratio(audit_events_recorded, audit_events_expected)
        reliability = max(0.0, min(1.0, availability))
        auditability = self._ratio(logs_with_correlation_id, total_logs)
        performance = self._latency_score()

        return QualityScore(
            security=security,
            data_integrity=data_integrity,
            reliability=reliability,
            auditability=auditability,
            performance=performance,
        )

    def slo_alert(
        self,
        *,
        failed_requests: int = 0,
        client_errors: int = 0,
        total_requests: int = 0,
    ) -> SloAlert:
        """
        A.17: Evaluate SLO breach conditions.

        Returns an SloAlert whose fields indicate which thresholds are violated.
        Wire this into your alerting pipeline: any_breach() == True → fire alert.
        """
        error_rate_pct = (failed_requests / total_requests * 100.0) if total_requests > 0 else 0.0
        client_error_pct = (client_errors / total_requests * 100.0) if total_requests > 0 else 0.0

        return SloAlert(
            p95_latency_breached=self._sla_latency_p95_ms > self.SLO_P95_LATENCY_MS,
            p99_latency_breached=self._sla_latency_p99_ms > self.SLO_P99_LATENCY_MS,
            error_rate_breached=error_rate_pct > self.SLO_ERROR_RATE_PCT,
            client_error_spike=client_error_pct > self.CLIENT_ERROR_SPIKE_PCT,
        )

    # ── helpers ──────────────────────────────────────────────────────────────

    @staticmethod
    def _ratio(numerator: int, denominator: int) -> float:
        if denominator <= 0:
            return 1.0   # no data = assume perfect (avoid false negatives at startup)
        return max(0.0, min(1.0, numerator / denominator))

    def _latency_score(self) -> float:
        """
        Linear decay: 1.0 at 0ms, 0.0 at target_latency_ms and beyond.
        """
        if self._target_latency_ms <= 0:
            return 1.0
        ratio = self._sla_latency_p95_ms / self._target_latency_ms
        return max(0.0, 1.0 - ratio)
