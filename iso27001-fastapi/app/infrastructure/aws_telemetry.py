"""
AWS CloudWatch + X-Ray telemetry emitter.

Sends custom metrics to CloudWatch and propagates X-Ray trace headers
so distributed traces are linked across services.

NOTE: Requires boto3 and aws-xray-sdk.
      Add to pyproject.toml under [project.optional-dependencies] → aws:
        boto3>=1.34
        aws-xray-sdk>=2.12

      Set env vars (or use IAM role — preferred in production):
        AWS_DEFAULT_REGION=eu-west-1
        AWS_CLOUDWATCH_NAMESPACE=ISO27001/API   (defaults to "ISO27001/API")

      If AWS credentials are absent, all methods are no-ops so the
      application still starts in dev/test environments.
"""
from __future__ import annotations

import os
import logging
from datetime import datetime, timezone
from typing import Any, Optional

logger = logging.getLogger(__name__)

_CW_NAMESPACE = os.getenv("AWS_CLOUDWATCH_NAMESPACE", "ISO27001/API")
_AWS_REGION = os.getenv("AWS_DEFAULT_REGION", "eu-west-1")


def _cloudwatch_client() -> Any:
    """Return a boto3 CloudWatch client, or None if boto3 is not installed."""
    try:
        import boto3  # type: ignore[import-not-found]
        return boto3.client("cloudwatch", region_name=_AWS_REGION)
    except ImportError:
        logger.debug("boto3 not installed — CloudWatch metrics disabled")
        return None


class CloudWatchEmitter:
    """
    Emits custom CloudWatch metrics for the ISO 27001 telemetry contract.

    Metric names follow the CloudWatch naming convention (PascalCase).
    All metrics include a "Service" dimension for per-service filtering.
    """

    def __init__(self, service_name: str, environment: str = "production") -> None:
        self._service = service_name
        self._env = environment
        self._cw: Any = _cloudwatch_client()

    # ── public API ───────────────────────────────────────────────────────────

    def emit_request(
        self,
        *,
        method: str,
        path: str,
        status_code: int,
        duration_ms: float,
    ) -> None:
        """Record HTTP request count and latency."""
        self._put_metric("RequestCount", 1, "Count", [
            {"Name": "Method", "Value": method},
            {"Name": "StatusCode", "Value": str(status_code)},
        ])
        self._put_metric("RequestLatency", duration_ms, "Milliseconds", [
            {"Name": "Path", "Value": path},
        ])
        if status_code >= 500:
            self._put_metric("ServerErrors", 1, "Count")

    def emit_auth_failure(self) -> None:
        """A.9: Track authentication failures for anomaly detection."""
        self._put_metric("AuthFailures", 1, "Count")

    def emit_rate_limit_hit(self) -> None:
        """A.17: Track rate limit events."""
        self._put_metric("RateLimitHits", 1, "Count")

    def emit_error_budget(self, budget_consumed_pct: float) -> None:
        """A.17: Publish error budget consumption as a gauge."""
        self._put_metric("ErrorBudgetConsumedPct", budget_consumed_pct, "Percent")

    def emit_quality_score(self, composite_score: float) -> None:
        """Publish the composite quality score (0–1 mapped to 0–100)."""
        self._put_metric("QualityScore", composite_score * 100, "Percent")

    # ── internal ─────────────────────────────────────────────────────────────

    def _put_metric(
        self,
        name: str,
        value: float,
        unit: str,
        extra_dimensions: Optional[list[dict[str, str]]] = None,
    ) -> None:
        if self._cw is None:
            return

        dimensions = [
            {"Name": "Service", "Value": self._service},
            {"Name": "Environment", "Value": self._env},
        ]
        if extra_dimensions:
            dimensions.extend(extra_dimensions)

        try:
            self._cw.put_metric_data(
                Namespace=_CW_NAMESPACE,
                MetricData=[{
                    "MetricName": name,
                    "Dimensions": dimensions,
                    "Timestamp": datetime.now(timezone.utc),
                    "Value": value,
                    "Unit": unit,
                }],
            )
        except Exception as exc:  # noqa: BLE001
            # Never let telemetry failure crash the application
            logger.warning("CloudWatch emit failed: %s", exc)


class XRayTracer:
    """
    Propagates AWS X-Ray trace context.

    Reads the X-Amzn-Trace-Id header from incoming requests and adds
    it to outgoing log entries so traces are linked in the X-Ray console.

    Full SDK integration (aws-xray-sdk) is opt-in; this class works
    without it by treating the header as a pass-through correlation ID.
    """

    HEADER = "X-Amzn-Trace-Id"

    @staticmethod
    def extract_trace_id(headers: dict[str, str]) -> Optional[str]:
        """Extract the X-Ray trace ID from request headers."""
        raw: str | None = headers.get(XRayTracer.HEADER) or headers.get(XRayTracer.HEADER.lower())
        if not raw:
            return None
        # Format: Root=1-xxxxxxxx-xxxxxxxxxxxxxxxxxxxx;Parent=xxxx;Sampled=1
        for part in raw.split(";"):
            if part.startswith("Root="):
                return part[5:]
        return raw

    @staticmethod
    def begin_segment(name: str, trace_id: Optional[str] = None) -> None:
        """Start an X-Ray segment if aws-xray-sdk is available."""
        try:
            from aws_xray_sdk.core import xray_recorder  # type: ignore[import-untyped,import-not-found]
            xray_recorder.begin_segment(name, traceid=trace_id)
        except ImportError:
            pass

    @staticmethod
    def end_segment() -> None:
        """End an X-Ray segment if aws-xray-sdk is available."""
        try:
            from aws_xray_sdk.core import xray_recorder  # type: ignore[import-untyped,import-not-found]
            xray_recorder.end_segment()
        except ImportError:
            pass


# Module-level singletons — service name resolved from settings at import time
def _build_emitter() -> CloudWatchEmitter:
    try:
        from app.config.settings import settings
        return CloudWatchEmitter(
            service_name=settings.APP_NAME,
            environment=settings.APP_ENV,
        )
    except Exception:  # noqa: BLE001
        return CloudWatchEmitter(service_name="iso27001-api")


cw_emitter = _build_emitter()
xray = XRayTracer()
