from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse
from fastapi.middleware.cors import CORSMiddleware

from app.config.settings import settings
from app.core.middleware import CorrelationIdMiddleware, RateLimitMiddleware
from app.core.database import Base, engine
from app.core.events import event_bus
from app.core.exceptions import APIError
from app.core.metrics import get_metrics
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

    # Middleware Stack
    app.add_middleware(CorrelationIdMiddleware)
    app.add_middleware(RateLimitMiddleware)
    app.add_middleware(
        CORSMiddleware,
        allow_origins=["*"], # Configure properly in production
        allow_methods=["*"],
        allow_headers=["*"],
    )

    # Routers
    app.include_router(health.router)
    app.include_router(auth.router, prefix="/api/v1/auth", tags=["auth"])
    app.include_router(users.router, prefix="/api/v1/users", tags=["users"])

    # Telemetry
    app.add_route("/metrics", get_metrics)

    # Event Listeners
    event_bus.subscribe(UserCreated, audit_listener)

    # Global Exception Handler (A.14)
    @app.exception_handler(APIError)
    async def api_error_handler(request: Request, exc: APIError):
        return JSONResponse(
            status_code=exc.status_code,
            content=create_error_response(
                code=exc.code,
                message=exc.message,
                request_id=getattr(request.state, "request_id", "unknown"),
                details=exc.details,
            ),
        )

    return app

app = create_app()