import json
import logging
import contextvars
from datetime import datetime, timezone
from app.config.settings import settings

# Context variable for Correlation ID
request_id_ctx: contextvars.ContextVar[str] = contextvars.ContextVar("request_id", default="system")

def get_correlation_id() -> str:
    return request_id_ctx.get()

class StructuredLogger:
    """
    A.12: JSON structured logger with automatic sensitive data redaction.
    Every log entry includes correlation ID for request tracing.
    """
    REDACTED_FIELDS = frozenset({
        "password", "token", "secret", "authorization", "api_key",
        "credit_card", "ssn", "refresh_token", "cookie",
    })

    def __init__(self, name: str) -> None:
        self._logger = logging.getLogger(name)
        handler = logging.StreamHandler()
        self._logger.addHandler(handler)
        self._logger.setLevel(logging.DEBUG if settings.APP_DEBUG else logging.INFO)

    def _redact(self, data: dict[str, object]) -> dict[str, object]:
        """A.12: Automatically strip sensitive fields. Never manual."""
        return {
            k: "[REDACTED]" if k.lower() in self.REDACTED_FIELDS
            else self._redact(v) if isinstance(v, dict)
            else v
            for k, v in data.items()
        }

    def _entry(self, level: str, message: str, **ctx: object) -> dict[str, object]:
        return {
            "timestamp": datetime.now(timezone.utc).isoformat(),
            "level": level,
            "message": message,
            "service": settings.APP_NAME,
            "version": settings.APP_VERSION,
            "environment": settings.APP_ENV,
            "request_id": get_correlation_id(),
            "context": self._redact(ctx) if ctx else None,
        }

    def info(self, msg: str, **ctx: object) -> None:
        self._logger.info(json.dumps(self._entry("INFO", msg, **ctx)))

    def warning(self, msg: str, **ctx: object) -> None:
        self._logger.warning(json.dumps(self._entry("WARNING", msg, **ctx)))

    def error(self, msg: str, **ctx: object) -> None:
        self._logger.error(json.dumps(self._entry("ERROR", msg, **ctx)))

    def audit(self, action: str, user_id: str, **ctx: object) -> None:
        """A.12: Immutable audit trail entry for compliance."""
        self._logger.info(json.dumps(self._entry(
            "AUDIT", f"audit.{action}",
            user_id=user_id, action=action,
            audited_at=datetime.now(timezone.utc).isoformat(),
            **ctx,
        )))

logger = StructuredLogger("api")