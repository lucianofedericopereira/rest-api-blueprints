"""
Re-exports domain event primitives for infrastructure and application consumers.
"""
from app.domain.events import DomainEvent, EventBus, event_bus

__all__ = ["DomainEvent", "EventBus", "event_bus"]
