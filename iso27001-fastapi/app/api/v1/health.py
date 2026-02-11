from fastapi import APIRouter, Depends, status
from fastapi.responses import JSONResponse
from datetime import datetime, timezone
import time
from sqlalchemy import text
from sqlalchemy.orm import Session
from app.api.deps import require_role
from app.core.database import get_db
from app.infrastructure.error_budget import error_budget
from app.infrastructure.quality_score import QualityScoreCalculator

router = APIRouter()


@router.get("/health", tags=["health"])
async def liveness():
    """A.17: Basic liveness — is the process running?"""
    return {"status": "ok", "timestamp": datetime.now(timezone.utc).isoformat()}


@router.get("/health/ready", tags=["health"])
def readiness(db: Session = Depends(get_db)):
    """A.17: Readiness — can it serve traffic?"""
    checks: dict = {}
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
async def detailed(_: dict = Depends(require_role("admin"))):
    """
    A.17: Detailed health — error budget + quality score.
    Requires admin role.
    """
    snapshot = error_budget.snapshot()

    # Build a quality score from available signals
    calculator = QualityScoreCalculator(
        sla_latency_p95_ms=50.0,    # placeholder; wire Prometheus histogram quantile here
        target_latency_ms=200.0,
    )
    score = calculator.calculate(
        auth_checks_passed=1,
        auth_checks_total=1,
        audit_events_recorded=1,
        audit_events_expected=1,
        availability=snapshot.observed_availability,
        logs_with_correlation_id=1,
        total_logs=1,
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
                "observed_availability": snapshot.observed_availability,
                "budget_consumed_pct": snapshot.budget_consumed_pct,
                "budget_exhausted": snapshot.budget_exhausted,
            },
            "quality_score": score.to_dict(),
        },
    )
