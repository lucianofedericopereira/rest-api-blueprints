"""
A.14: Centralized exception definitions and handlers.
All exceptions produce consistent, secure error responses.
"""

from typing import Any


class APIError(Exception):
    """Base exception for all API errors."""

    def __init__(
        self,
        code: str,
        message: str,
        status_code: int = 500,
        details: list[dict[str, Any]] | None = None,
    ):
        self.code = code
        self.message = message
        self.status_code = status_code
        self.details = details or []
        super().__init__(message)


class ValidationError(APIError):
    """A.14: Input validation failed."""

    def __init__(
        self,
        message: str = "Input validation failed",
        details: list[dict[str, Any]] | None = None,
    ):
        super().__init__(
            code="VALIDATION_ERROR",
            message=message,
            status_code=400,
            details=details,
        )


class AuthenticationError(APIError):
    """A.9: Authentication failed (invalid or missing credentials)."""

    def __init__(self, message: str = "Authentication required"):
        super().__init__(
            code="AUTHENTICATION_ERROR",
            message=message,
            status_code=401,
        )


class AuthorizationError(APIError):
    """A.9: Authorization failed (insufficient permissions)."""

    def __init__(self, message: str = "Access denied"):
        super().__init__(
            code="AUTHORIZATION_ERROR",
            message=message,
            status_code=403,
        )


class NotFoundError(APIError):
    """Resource not found."""

    def __init__(self, resource: str, resource_id: str):
        super().__init__(
            code="NOT_FOUND",
            message=f"{resource} with id '{resource_id}' not found",
            status_code=404,
        )


class RateLimitError(APIError):
    """A.17: Rate limit exceeded."""

    def __init__(self, retry_after: int):
        super().__init__(
            code="RATE_LIMIT_EXCEEDED",
            message="Too many requests. Please try again later.",
            status_code=429,
            details=[{"retry_after_seconds": retry_after}],
        )
        self.retry_after = retry_after


class ConflictError(APIError):
    """Resource conflict (e.g., duplicate email)."""

    def __init__(self, message: str = "Resource conflict"):
        super().__init__(
            code="CONFLICT",
            message=message,
            status_code=409,
        )


class InternalError(APIError):
    """A.14: Internal server error (details hidden from client)."""

    def __init__(self, internal_message: str = "Internal error"):
        # Never expose internal details to client
        super().__init__(
            code="INTERNAL_ERROR",
            message="An unexpected error occurred",
            status_code=500,
        )
        # Store for logging purposes only
        self.internal_message = internal_message
