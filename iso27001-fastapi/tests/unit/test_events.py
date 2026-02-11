"""Unit tests for domain events and EventBus."""
import pytest
from app.domain.users.events import UserCreated, DomainEvent
from app.core.events import EventBus


class TestUserCreated:
    def test_create_valid_event(self):
        event = UserCreated(user_id="usr_123", email_hash="abc123hash", role="viewer")
        assert event.user_id == "usr_123"
        assert event.email_hash == "abc123hash"
        assert event.role == "viewer"
        assert event.event_id  # auto-generated UUID
        assert event.occurred_at  # auto-generated datetime

    def test_missing_user_id_raises(self):
        with pytest.raises(ValueError, match="user_id is required"):
            UserCreated(user_id="", email_hash="abc", role="viewer")

    def test_missing_email_hash_raises(self):
        with pytest.raises(ValueError, match="email_hash is required"):
            UserCreated(user_id="usr_123", email_hash="", role="viewer")

    def test_missing_role_raises(self):
        with pytest.raises(ValueError, match="role is required"):
            UserCreated(user_id="usr_123", email_hash="abc", role="")

    def test_event_is_immutable(self):
        event = UserCreated(user_id="usr_123", email_hash="abc", role="viewer")
        with pytest.raises(Exception):  # frozen dataclass
            event.user_id = "other"  # type: ignore

    def test_to_log_context(self):
        event = UserCreated(user_id="usr_123", email_hash="abc", role="viewer")
        ctx = event.to_log_context()
        assert ctx["event_type"] == "UserCreated"
        assert "event_id" in ctx
        assert "occurred_at" in ctx


class TestEventBus:
    def test_subscribe_and_publish(self):
        bus = EventBus()
        received = []
        bus.subscribe(UserCreated, lambda e: received.append(e))

        event = UserCreated(user_id="u1", email_hash="h1", role="admin")
        bus.publish(event)

        assert len(received) == 1
        assert received[0] is event

    def test_publish_with_no_listener(self):
        bus = EventBus()
        event = UserCreated(user_id="u1", email_hash="h1", role="admin")
        bus.publish(event)  # should not raise

    def test_multiple_listeners(self):
        bus = EventBus()
        calls = []
        bus.subscribe(UserCreated, lambda e: calls.append("listener1"))
        bus.subscribe(UserCreated, lambda e: calls.append("listener2"))

        bus.publish(UserCreated(user_id="u1", email_hash="h1", role="viewer"))
        assert calls == ["listener1", "listener2"]
