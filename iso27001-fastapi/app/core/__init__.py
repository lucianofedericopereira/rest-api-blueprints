"""Core module containing cross-cutting concerns."""

from app.core.exceptions import (
    APIError,
    AuthenticationError,
    AuthorizationError,
    NotFoundError,
    RateLimitError,
    ValidationError,
)
from app.core.responses import ErrorResponse

__all__ = [
    "APIError",
    "AuthenticationError",
    "AuthorizationError",
    "NotFoundError",
    "RateLimitError",
    "ValidationError",
    "ErrorResponse",
]
