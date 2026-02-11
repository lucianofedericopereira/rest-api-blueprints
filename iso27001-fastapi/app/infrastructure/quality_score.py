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
class QualityScore:
    security: float          # 0–1  (JWT validity, RBAC enforcement, encryption)
    data_integrity: float    # 0–1  (audit log completeness, event dispatch success)
    reliability: float       # 0–1  (1 - error_rate, derived from ErrorBudgetTracker)
    auditability: float      # 0–1  (structured log coverage, correlation ID presence)
    performance: float       # 0–1  (P95 latency vs SLA threshold)

    def composite(self) -> float:
        """
        Weighted composite score.
        Returns a value between 0.0 and 1.0.
        """
        return (
            self.security        * 0.40
            + self.data_integrity  * 0.20
            + self.reliability     * 0.15
            + self.auditability    * 0.15
            + self.performance     * 0.05
        )

    def passes_gate(self) -> bool:
        """True if this score meets the production deployment gate."""
        return self.composite() >= PRODUCTION_GATE

    def to_dict(self) -> dict:
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
    Assembles a QualityScore from live runtime signals.

    Inject the dependencies it needs (error budget, latency histogram, etc.)
    rather than reaching for global state directly — makes it unit-testable.
    """

    def __init__(
        self,
        *,
        sla_latency_p95_ms: float = 200.0,
        target_latency_ms: float = 500.0,
    ) -> None:
        """
        Args:
            sla_latency_p95_ms: Observed P95 latency in milliseconds (from metrics).
            target_latency_ms: Acceptable P95 latency ceiling from SLA.
        """
        self._sla_latency_p95_ms = sla_latency_p95_ms
        self._target_latency_ms = target_latency_ms

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
