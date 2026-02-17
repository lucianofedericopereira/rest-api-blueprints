"""
A.9 / A.17: Redis-backed sliding-window rate limiter with three tiers.

  auth   — 10  req/min per IP  (login — A.9: brute-force protection)
  write  — 30  req/min per IP  (POST/PUT/PATCH/DELETE — A.17: write protection)
  global — 100 req/min per IP  (everything else — A.17: DoS protection)

Lua script ensures atomic ZREMRANGEBYSCORE + ZCARD + ZADD (no TOCTOU race).
Falls back to an in-process sliding window when Redis is unavailable.
"""

import time
from typing import Any
from fastapi import Request, HTTPException, status

_LIMITS: dict[str, int] = {
    "auth":   10,
    "write":  30,
    "global": 100,
}

_LUA_SCRIPT = """
local key = KEYS[1]
local limit = tonumber(ARGV[1])
local now = tonumber(ARGV[2])
local window = tonumber(ARGV[3])
local clear_before = now - window

redis.call('ZREMRANGEBYSCORE', key, 0, clear_before)
local count = redis.call('ZCARD', key)

if count < limit then
    redis.call('ZADD', key, now, tostring(now))
    redis.call('EXPIRE', key, window)
    return 0
else
    return 1
end
"""

# ── in-process fallback (dev / no-Redis) ─────────────────────────────────────
_local_windows: dict[str, list[float]] = {}


def _local_check(key: str, limit: int, window: int) -> bool:
    now = time.time()
    entries = [t for t in _local_windows.get(key, []) if t > now - window]
    if len(entries) >= limit:
        _local_windows[key] = entries
        return False
    entries.append(now)
    _local_windows[key] = entries
    return True


def _redis_client() -> Any:
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


def _tier(request: Request) -> str:
    path = request.url.path
    method = request.method.upper()
    if "/auth/" in path or path.endswith("/auth"):
        return "auth"
    if method in {"POST", "PUT", "PATCH", "DELETE"}:
        return "write"
    return "global"


class RedisRateLimiter:
    """
    A.17: Tiered sliding-window rate limiter.
    Redis-backed with silent in-process fallback.
    """

    async def check(self, request: Request) -> None:
        tier = _tier(request)
        limit = _LIMITS[tier]
        window = 60
        client_ip = request.client.host if request.client else "unknown"
        key = f"rate_limit:{tier}:{client_ip}"
        now = time.time()

        r = _redis_client()
        if r is not None:
            allowed = int(r.eval(_LUA_SCRIPT, 1, key, limit, now, window)) == 0
        else:
            allowed = _local_check(key, limit, window)

        if not allowed:
            raise HTTPException(
                status_code=status.HTTP_429_TOO_MANY_REQUESTS,
                detail={"code": "RATE_LIMIT", "message": f"Rate limit exceeded ({tier}: {limit}/min)"},
            )