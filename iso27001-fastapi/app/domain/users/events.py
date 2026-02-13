"""
User domain events.
"""
from dataclasses import dataclass, field
from datetime import datetime, timezone
import uuid

from app.domain.events import DomainEvent as _BaseDomainEvent


@dataclass(frozen=True)
class DomainEvent(_BaseDomainEvent):
    """Telemetry-enriched base for all domain events."""
    event_id: str = field(default_factory=lambda: str(uuid.uuid4()))
    occurred_at: datetime = field(default_factory=lambda: datetime.now(timezone.utc))

    def to_log_context(self) -> dict[str, str]:
        return {
            "event_type": self.__class__.__name__,
            "event_id": self.event_id,
            "occurred_at": self.occurred_at.isoformat(),
        }


@dataclass(frozen=True)
class UserCreated(DomainEvent):
    """
    Emitted after a User aggregate is persisted.
    A.12: email_hash — never log or store raw email in events.
    """
    # Fix: give required fields sentinel defaults; validate in __post_init__
    # so Python's dataclass inheritance rule (defaults before non-defaults) is satisfied.
    user_id: str = ""
    email_hash: str = ""   # A.12: SHA-256 hash of email — never raw PII
    role: str = ""

    def __post_init__(self) -> None:
        if not self.user_id:
            raise ValueError("UserCreated.user_id is required")
        if not self.email_hash:
            raise ValueError("UserCreated.email_hash is required")
        if not self.role:
            raise ValueError("UserCreated.role is required")
