"""
Event dispatcher — thin re-export of the core EventBus singleton.
Infrastructure layer wires listeners at startup; domain layer only calls publish().

Dependency direction: infrastructure → core  ✓
"""
from app.core.events import EventBus, event_bus

__all__ = ["EventBus", "event_bus"]
