"""
A.14: Consistent API response structures.
Never expose stack traces, SQL, or internal paths.
"""

from typing import Any, Generic, TypeVar

from pydantic import BaseModel, Field

T = TypeVar("T")


class ErrorDetail(BaseModel):
    """Individual error detail for validation errors."""

    field: str
    message: str
    code: str


class ErrorBody(BaseModel):
    """Error response body structure."""

    code: str
    message: str
    request_id: str
    details: list[ErrorDetail] = Field(default_factory=list)


class ErrorResponse(BaseModel):
    """
    A.14: Consistent error response format.
    Never includes stack traces or internal details.
    """

    error: ErrorBody


class SuccessResponse(BaseModel, Generic[T]):
    """Generic success response wrapper."""

    data: T
    meta: dict[str, Any] | None = None


class PaginatedMeta(BaseModel):
    """Pagination metadata."""

    page: int
    per_page: int
    total: int
    total_pages: int


class PaginatedResponse(BaseModel, Generic[T]):
    """Paginated list response."""

    data: list[T]
    meta: PaginatedMeta


def create_error_response(
    code: str,
    message: str,
    request_id: str,
    details: list[dict[str, Any]] | None = None,
) -> dict[str, Any]:
    """Create a consistent error response dict."""
    error_details = []
    if details:
        for detail in details:
            if "field" in detail and "message" in detail:
                error_details.append(
                    ErrorDetail(
                        field=detail.get("field", "unknown"),
                        message=detail.get("message", "Unknown error"),
                        code=detail.get("code", "UNKNOWN"),
                    )
                )

    return ErrorResponse(
        error=ErrorBody(
            code=code,
            message=message,
            request_id=request_id,
            details=error_details,
        )
    ).model_dump()
