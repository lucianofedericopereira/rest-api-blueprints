"""
A.9: Brute-force login protection.

Tracks failed authentication attempts per account identifier (email).
Uses Redis when available (cross-process, survives restarts); falls back to
an in-process dict for dev/test environments without Redis.

Policy:
  - MAX_ATTEMPTS  : 5 consecutive failures trigger a lockout
  - LOCKOUT_TTL   : 15 minutes (900 seconds)
  - Window resets on a successful login (clear())

Install Redis for production accuracy:
  REDIS_URL=redis://127.0.0.1:6379
"""

import time
from typing import Any
from fastapi import HTTPException, status

_MAX_ATTEMPTS: int = 5
_LOCKOUT_TTL: int = 900  # seconds (15 minutes)
_KEY_PREFIX = "brute_force:"

# ── in-process fallback storage ──────────────────────────────────────────────
# {email: {"count": int, "locked_until": float}}
_local: dict[str, dict[str, float | int]] = {}


def _redis_client() -> Any:
    """Return a redis.Redis client or None if unavailable."""
    try:
        import redis as _redis
        from app.config.settings import settings

        client = _redis.Redis.from_url(
            settings.REDIS_URL, encoding="utf-8", decode_responses=True, socket_connect_timeout=0.5
        )
        client.ping()
        return client
    except Exception:
        return None


class BruteForceGuard:
    """
    Thread-safe (via Redis atomics) brute-force guard.
    Degrades gracefully to in-process counters when Redis is unavailable.
    """

    def check(self, identifier: str) -> None:
        """Raise HTTP 429 if the account is currently locked out."""
        r = _redis_client()
        key_locked = f"{_KEY_PREFIX}{identifier}:locked_until"

        if r is not None:
            locked_until = r.get(key_locked)
            if locked_until and float(locked_until) > time.time():
                raise HTTPException(
                    status_code=status.HTTP_429_TOO_MANY_REQUESTS,
                    detail={
                        "code": "ACCOUNT_LOCKED",
                        "message": "Too many failed attempts. Account temporarily locked.",
                    },
                )
        else:
            entry = _local.get(identifier)
            if entry and float(entry.get("locked_until", 0)) > time.time():
                raise HTTPException(
                    status_code=status.HTTP_429_TOO_MANY_REQUESTS,
                    detail={
                        "code": "ACCOUNT_LOCKED",
                        "message": "Too many failed attempts. Account temporarily locked.",
                    },
                )

    def record_failure(self, identifier: str) -> None:
        """Increment failure counter; lock the account on threshold breach."""
        r = _redis_client()
        key_count = f"{_KEY_PREFIX}{identifier}:count"
        key_locked = f"{_KEY_PREFIX}{identifier}:locked_until"

        if r is not None:
            count = r.incr(key_count)
            r.expire(key_count, _LOCKOUT_TTL)
            if int(count) >= _MAX_ATTEMPTS:
                locked_until = time.time() + _LOCKOUT_TTL
                r.set(key_locked, locked_until, ex=_LOCKOUT_TTL)
                r.delete(key_count)
        else:
            entry = _local.setdefault(identifier, {"count": 0, "locked_until": 0.0})
            entry["count"] = int(entry["count"]) + 1
            if int(entry["count"]) >= _MAX_ATTEMPTS:
                entry["locked_until"] = time.time() + _LOCKOUT_TTL
                entry["count"] = 0

    def clear(self, identifier: str) -> None:
        """Clear failure counters after a successful login."""
        r = _redis_client()
        if r is not None:
            r.delete(
                f"{_KEY_PREFIX}{identifier}:count",
                f"{_KEY_PREFIX}{identifier}:locked_until",
            )
        else:
            _local.pop(identifier, None)


# Module-level singleton
brute_force_guard = BruteForceGuard()
