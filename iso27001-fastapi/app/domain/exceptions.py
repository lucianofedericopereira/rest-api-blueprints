"""
Domain exceptions — pure business rule violations, no infrastructure dependencies.
"""


class DomainError(Exception):
    """Base exception for domain rule violations."""


class ConflictError(DomainError):
    """Resource conflict (e.g., duplicate email)."""

    def __init__(self, message: str = "Resource conflict") -> None:
        super().__init__(message)
        self.message = message
