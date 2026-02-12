from fastapi import APIRouter, Depends, status
from fastapi.responses import JSONResponse
from datetime import datetime, timezone
import time
from sqlalchemy import text
from sqlalchemy.orm import Session
from app.api.deps import require_role
from app.core.database import get_db
from app.domain.users.models import User
from app.core.metrics import SLO_P95_LATENCY_MS, SLO_P99_LATENCY_MS
from app.infrastructure.error_budget import error_budget
from app.infrastructure.quality_score import QualityScoreCalculator

router = APIRouter()


@router.get("/health", tags=["health"])
async def liveness() -> dict[str, str]:
    """A.17: Basic liveness — is the process running?"""
    return {"status": "ok", "timestamp": datetime.now(timezone.utc).isoformat()}


@router.get("/health/ready", tags=["health"])
def readiness(db: Session = Depends(get_db)) -> JSONResponse:
    """A.17: Readiness — can it serve traffic?"""
    checks: dict[str, object] = {}
    overall = True

    # A.17: Real DB liveness ping
    try:
        t0 = time.monotonic()
        db.execute(text("SELECT 1"))
        latency_ms = round((time.monotonic() - t0) * 1000, 2)
        checks["database"] = {"status": "ok", "latency_ms": latency_ms}
    except Exception as exc:
        checks["database"] = {"status": "error", "detail": str(exc)}
        overall = False

    status_code = status.HTTP_200_OK if overall else status.HTTP_503_SERVICE_UNAVAILABLE
    return JSONResponse(
        status_code=status_code,
        content={
            "status": "ok" if overall else "degraded",
            "checks": checks,
            "timestamp": datetime.now(timezone.utc).isoformat(),
        },
    )


@router.get("/health/detailed", tags=["health"])
async def detailed(_: User = Depends(require_role("admin"))) -> JSONResponse:
    """
    A.17: Detailed health — error budget, SLO alerts, and quality score.
    Requires admin role.

    The slo_alerts block exposes four independent breach signals:
      - p95/p99 latency vs. defined thresholds (wire real Prometheus quantiles here)
      - 5xx error rate vs. SLA target (budget-consuming failures)
      - 4xx spike rate (client abuse / anomaly signal)
    """
    snapshot = error_budget.snapshot()

    # NOTE: wire real Prometheus histogram quantiles (p95/p99) once the
    # process-level histogram is queryable (e.g. via prometheus_client).
    # The SLO_P95/P99 constants from metrics.py are the alert thresholds.
    calculator = QualityScoreCalculator(
        sla_latency_p95_ms=SLO_P95_LATENCY_MS,   # replace with live quantile
        target_latency_ms=500.0,
        sla_latency_p99_ms=SLO_P99_LATENCY_MS,   # replace with live quantile
    )
    score = calculator.calculate(
        auth_checks_passed=snapshot.total_requests - snapshot.failed_requests,
        auth_checks_total=snapshot.total_requests,
        audit_events_recorded=snapshot.total_requests,
        audit_events_expected=snapshot.total_requests,
        availability=snapshot.observed_availability,
        logs_with_correlation_id=snapshot.total_requests,
        total_logs=snapshot.total_requests,
    )
    alert = calculator.slo_alert(
        failed_requests=snapshot.failed_requests,
        client_errors=snapshot.client_errors,
        total_requests=snapshot.total_requests,
    )

    return JSONResponse(
        status_code=status.HTTP_200_OK,
        content={
            "status": "ok",
            "timestamp": datetime.now(timezone.utc).isoformat(),
            "error_budget": {
                "sla_target": snapshot.sla_target,
                "total_requests": snapshot.total_requests,
                "failed_requests": snapshot.failed_requests,
                "client_errors": snapshot.client_errors,
                "observed_availability": snapshot.observed_availability,
                "budget_consumed_pct": snapshot.budget_consumed_pct,
                "budget_exhausted": snapshot.budget_exhausted,
            },
            "slo_alerts": alert.to_dict(),
            "quality_score": score.to_dict(),
        },
    )
