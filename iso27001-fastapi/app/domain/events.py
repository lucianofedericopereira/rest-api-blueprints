"""
Domain event primitives — pure domain concepts, no infrastructure dependencies.
"""
from collections import defaultdict
from typing import Any, Callable, Type, TypeVar


class DomainEvent:
    """
    Base domain event.  Concrete events inherit from this class.
    Telemetry context (event_id, occurred_at) is added in
    app.domain.users.events which re-declares this with dataclass.
    """


T = TypeVar("T", bound=DomainEvent)
Listener = Callable[[Any], None]


class EventBus:
    """
    Simple synchronous event bus for domain events.
    Decouples domain logic from side effects (audit, notifications, metrics).
    """

    def __init__(self) -> None:
        self._listeners: dict[Type[DomainEvent], list[Listener]] = defaultdict(list)

    def subscribe(self, event_type: Type[T], listener: Listener) -> None:
        self._listeners[event_type].append(listener)

    def publish(self, event: DomainEvent) -> None:
        for listener in self._listeners.get(type(event), []):
            listener(event)


# Global singleton — wired at application startup in main.py
event_bus = EventBus()
