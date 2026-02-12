import uuid
from datetime import datetime, timezone

from sqlalchemy import Column, DateTime, String, JSON
from sqlalchemy.orm import Session

from app.core.database import Base, SessionLocal
from app.core.telemetry import logger, get_correlation_id
from app.domain.users.events import UserCreated, DomainEvent


class AuditLog(Base):  # type: ignore[misc]
    """
    A.12: Immutable audit trail entity.
    Stores WHO did WHAT, WHEN, and WHICH resource was affected.
    """
    __tablename__ = "audit_logs"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    action = Column(String, nullable=False)
    performed_by = Column(String, nullable=True)  # User ID or 'system'
    resource_type = Column(String, nullable=False)
    resource_id = Column(String, nullable=False)
    changes = Column(JSON, nullable=True)
    ip_address = Column(String, nullable=True)
    correlation_id = Column(String, nullable=False)
    created_at = Column(DateTime, default=lambda: datetime.now(timezone.utc))


class AuditService:
    """
    Service to persist audit logs.
    Typically called via event listeners to avoid coupling in domain services.
    """
    def record(
        self,
        action: str,
        resource_type: str,
        resource_id: str,
        performed_by: str = "system",
        changes: dict[str, str] | None = None,
    ) -> None:
        # Use a separate session to ensure audit logs are committed 
        # even if the main transaction fails (best effort)
        db: Session = SessionLocal()
        try:
            entry = AuditLog(
                action=action,
                performed_by=performed_by,
                resource_type=resource_type,
                resource_id=resource_id,
                changes=changes,
                correlation_id=get_correlation_id() or "unknown",
            )
            db.add(entry)
            db.commit()
            
            # Also log to structured logger for redundancy/shipping
            logger.audit(action, user_id=performed_by, resource_id=resource_id)
        except Exception as e:
            logger.error(f"Failed to write audit log: {e}")
        finally:
            db.close()


def audit_listener(event: DomainEvent) -> None:
    """Generic listener to route domain events to audit service."""
    service = AuditService()
    
    if isinstance(event, UserCreated):
        service.record(
            action="user.created",
            resource_type="user",
            resource_id=event.user_id,
            performed_by="system", # Registration is usually public/system
            changes={"email_hash": event.email_hash, "role": event.role}
        )