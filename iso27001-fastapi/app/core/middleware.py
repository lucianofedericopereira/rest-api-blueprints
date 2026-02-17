import uuid
import time
from starlette.middleware.base import BaseHTTPMiddleware, RequestResponseEndpoint
from starlette.responses import Response
from starlette.types import ASGIApp
from fastapi import Request
from fastapi.responses import JSONResponse
from app.core.telemetry import request_id_ctx, logger
from app.core.metrics import REQUEST_COUNT, REQUEST_LATENCY, record_error_class
from app.core.rate_limiter import RedisRateLimiter
from app.infrastructure.error_budget import error_budget
from app.infrastructure.aws_telemetry import cw_emitter, xray


class CorrelationIdMiddleware(BaseHTTPMiddleware):
    """
    A.12: Assigns a correlation ID to every request.
    Propagates through logs, events, downstream calls, and responses.
    Also extracts the AWS X-Ray trace ID when present.
    """
    async def dispatch(self, request: Request, call_next: RequestResponseEndpoint) -> Response:
        # Preserve client-provided ID or generate new one
        request_id = request.headers.get("X-Request-ID", str(uuid.uuid4()))
        request_id_ctx.set(request_id)
        request.state.request_id = request_id

        # X-Ray trace context (no-op when header is absent)
        trace_id = xray.extract_trace_id(dict(request.headers))
        request.state.trace_id = trace_id

        start = time.perf_counter()

        # Log request start
        logger.info("request.started", method=request.method, path=request.url.path)

        response = await call_next(request)

        duration_s = time.perf_counter() - start
        duration_ms = round(duration_s * 1000, 2)

        # Prometheus metrics
        REQUEST_COUNT.labels(method=request.method, endpoint=request.url.path, status_code=response.status_code).inc()
        REQUEST_LATENCY.labels(method=request.method, endpoint=request.url.path).observe(duration_s)

        # A.17: 4xx/5xx separation — record error class for alert-level visibility
        record_error_class(response.status_code)

        # A.17: Record in error budget (5xx responses consume budget; 4xx tracked separately)
        error_budget.record(status_code=response.status_code)

        # CloudWatch custom metrics (no-op when boto3 is absent)
        cw_emitter.emit_request(
            method=request.method,
            path=request.url.path,
            status_code=response.status_code,
            duration_ms=duration_ms,
        )

        # Inject correlation headers into response for client-side tracing
        response.headers["X-Request-ID"] = request_id
        response.headers["X-Response-Time"] = f"{duration_ms}ms"
        if trace_id:
            response.headers["X-Amzn-Trace-Id"] = trace_id

        # Log request completion
        logger.info("request.completed", status_code=response.status_code, duration_ms=duration_ms)
        return response


class RateLimitMiddleware(BaseHTTPMiddleware):
    """
    A.17: Rate limiting middleware to protect availability.
    """
    def __init__(self, app: ASGIApp) -> None:
        super().__init__(app)
        self.limiter = RedisRateLimiter()

    async def dispatch(self, request: Request, call_next: RequestResponseEndpoint) -> Response:
        try:
            await self.limiter.check(request)
        except Exception as e:
            # Handle HTTPException from limiter
            if hasattr(e, "status_code") and e.status_code == 429:
                cw_emitter.emit_rate_limit_hit()
                return JSONResponse(status_code=429, content={"error": {"code": "RATE_LIMIT", "message": "Too many requests"}})
            raise e
        return await call_next(request)


class SecurityHeadersMiddleware(BaseHTTPMiddleware):
    """
    A.10: Injects security headers on every HTTP response.
    Enforces HSTS, prevents clickjacking, stops MIME sniffing, restricts CSP.
    Mirrors the Symfony SecurityHeaderSubscriber and Laravel SecurityHeadersMiddleware.
    """

    async def dispatch(self, request: Request, call_next: RequestResponseEndpoint) -> Response:
        response = await call_next(request)
        response.headers["Strict-Transport-Security"] = "max-age=31536000; includeSubDomains"
        response.headers["X-Frame-Options"] = "DENY"
        response.headers["X-Content-Type-Options"] = "nosniff"
        response.headers["Referrer-Policy"] = "strict-origin-when-cross-origin"
        response.headers["Content-Security-Policy"] = "default-src 'none'; frame-ancestors 'none'"
        response.headers["Permissions-Policy"] = "geolocation=(), microphone=(), camera=()"
        response.headers["Cross-Origin-Opener-Policy"] = "same-origin"
        response.headers["Cross-Origin-Embedder-Policy"] = "require-corp"
        if "server" in response.headers:
            del response.headers["server"]
        if "x-powered-by" in response.headers:
            del response.headers["x-powered-by"]
        return response
