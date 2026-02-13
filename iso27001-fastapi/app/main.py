from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse, Response
from fastapi.middleware.cors import CORSMiddleware
from starlette.requests import Request as StarletteRequest

from app.config.settings import settings
from app.core.middleware import CorrelationIdMiddleware, RateLimitMiddleware, SecurityHeadersMiddleware
from app.core.database import Base, engine
from app.core.events import event_bus
from app.core.exceptions import APIError
from app.core.metrics import get_metrics
from app.domain.exceptions import ConflictError as DomainConflictError
from app.core.responses import create_error_response
from app.api.v1 import health, auth, users
from app.domain.users.events import UserCreated
from app.infrastructure.audit import AuditLog, audit_listener

def create_app() -> FastAPI:
    # Create tables (for dev only - use Alembic in prod)
    Base.metadata.create_all(bind=engine)

    app = FastAPI(
        title=settings.APP_NAME,
        version=settings.APP_VERSION,
        docs_url="/docs" if settings.APP_ENV != "production" else None,
        redoc_url=None,
    )

    # Middleware Stack (added outermost to innermost — Starlette reverses order)
    app.add_middleware(SecurityHeadersMiddleware)   # A.10: security headers on every response
    app.add_middleware(CorrelationIdMiddleware)
    app.add_middleware(RateLimitMiddleware)
    # A.9: Explicit CORS allowlist — no wildcard; configured via CORS_ALLOWED_ORIGINS env var
    allowed_origins = [o.strip() for o in settings.CORS_ALLOWED_ORIGINS.split(",") if o.strip()]
    app.add_middleware(
        CORSMiddleware,
        allow_origins=allowed_origins,
        allow_methods=["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"],
        allow_headers=["Authorization", "Content-Type", "X-Request-ID"],
        expose_headers=["X-Request-ID", "X-Response-Time"],
    )

    # Routers
    app.include_router(health.router)
    app.include_router(auth.router, prefix="/api/v1/auth", tags=["auth"])
    app.include_router(users.router, prefix="/api/v1/users", tags=["users"])

    # Telemetry
    async def metrics_endpoint(request: StarletteRequest) -> Response:
        return get_metrics()
    app.add_route("/metrics", metrics_endpoint)

    # Event Listeners
    event_bus.subscribe(UserCreated, audit_listener)

    # Global Exception Handler (A.14)
    @app.exception_handler(APIError)
    async def api_error_handler(request: Request, exc: APIError) -> JSONResponse:
        return JSONResponse(
            status_code=exc.status_code,
            content=create_error_response(
                code=exc.code,
                message=exc.message,
                request_id=getattr(request.state, "request_id", "unknown"),
                details=exc.details,
            ),
        )

    @app.exception_handler(DomainConflictError)
    async def domain_conflict_handler(request: Request, exc: DomainConflictError) -> JSONResponse:
        return JSONResponse(
            status_code=409,
            content=create_error_response(
                code="CONFLICT",
                message=exc.message,
                request_id=getattr(request.state, "request_id", "unknown"),
            ),
        )

    return app

app = create_app()