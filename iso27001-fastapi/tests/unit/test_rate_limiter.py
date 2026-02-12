"""Unit tests for the Redis-backed sliding-window rate limiter."""
import pytest
from unittest.mock import MagicMock, patch
from fastapi import HTTPException
from starlette.testclient import TestClient
from starlette.requests import Request as StarletteRequest

from app.core.rate_limiter import RedisRateLimiter, _local_check, _local_windows, _tier


# ── helpers ───────────────────────────────────────────────────────────────────

def _make_request(path: str = "/api/v1/users", method: str = "GET", ip: str = "127.0.0.1") -> StarletteRequest:
    scope = {
        "type": "http",
        "method": method.upper(),
        "path": path,
        "query_string": b"",
        "headers": [],
        "client": (ip, 9000),
    }
    return StarletteRequest(scope)


# ── tier selection ────────────────────────────────────────────────────────────

class TestTierSelection:
    def test_auth_path_returns_auth_tier(self):
        assert _tier(_make_request("/api/v1/auth/token", "POST")) == "auth"

    def test_auth_path_get_also_auth_tier(self):
        assert _tier(_make_request("/api/v1/auth/refresh", "GET")) == "auth"

    def test_write_method_returns_write_tier(self):
        assert _tier(_make_request("/api/v1/users", "POST")) == "write"
        assert _tier(_make_request("/api/v1/users/1", "PATCH")) == "write"
        assert _tier(_make_request("/api/v1/users/1", "DELETE")) == "write"

    def test_get_on_non_auth_returns_global_tier(self):
        assert _tier(_make_request("/api/v1/users", "GET")) == "global"

    def test_health_returns_global_tier(self):
        assert _tier(_make_request("/health", "GET")) == "global"


# ── in-process fallback ───────────────────────────────────────────────────────

class TestLocalFallback:
    def setup_method(self):
        _local_windows.clear()

    def test_allows_requests_under_limit(self):
        for _ in range(5):
            assert _local_check("test_key", 10, 60) is True

    def test_blocks_when_limit_reached(self):
        for _ in range(10):
            _local_check("limit_key", 10, 60)
        assert _local_check("limit_key", 10, 60) is False

    def test_different_keys_are_independent(self):
        for _ in range(10):
            _local_check("key_a", 10, 60)
        # key_a is exhausted; key_b should still be allowed
        assert _local_check("key_b", 10, 60) is True


# ── RedisRateLimiter.check() — no Redis (in-process fallback) ─────────────────

class TestRedisRateLimiterNoRedis:
    def setup_method(self):
        _local_windows.clear()

    @pytest.mark.asyncio
    async def test_allows_request_under_limit(self):
        limiter = RedisRateLimiter()
        request = _make_request("/api/v1/users", "GET")

        with patch("app.core.rate_limiter._redis_client", return_value=None):
            # Should not raise
            await limiter.check(request)

    @pytest.mark.asyncio
    async def test_raises_429_when_global_limit_exceeded(self):
        limiter = RedisRateLimiter()
        request = _make_request("/api/v1/users", "GET")

        with patch("app.core.rate_limiter._redis_client", return_value=None):
            # Exhaust the global limit (100 req/min)
            for _ in range(100):
                _local_check(f"rate_limit:global:{request.client.host}", 100, 60)

            with pytest.raises(HTTPException) as exc_info:
                await limiter.check(request)

        assert exc_info.value.status_code == 429

    @pytest.mark.asyncio
    async def test_auth_tier_has_stricter_limit(self):
        limiter = RedisRateLimiter()
        request = _make_request("/api/v1/auth/token", "POST")

        with patch("app.core.rate_limiter._redis_client", return_value=None):
            # Exhaust the auth limit (10 req/min)
            for _ in range(10):
                _local_check(f"rate_limit:auth:{request.client.host}", 10, 60)

            with pytest.raises(HTTPException) as exc_info:
                await limiter.check(request)

        assert exc_info.value.status_code == 429
        assert exc_info.value.detail["code"] == "RATE_LIMIT"


# ── RedisRateLimiter.check() — with Redis ─────────────────────────────────────

class TestRedisRateLimiterWithRedis:
    @pytest.mark.asyncio
    async def test_allows_when_lua_returns_zero(self):
        """Lua script returns 0 → allowed."""
        mock_redis = MagicMock()
        mock_redis.eval.return_value = 0

        limiter = RedisRateLimiter()
        request = _make_request("/api/v1/users", "GET")

        with patch("app.core.rate_limiter._redis_client", return_value=mock_redis):
            await limiter.check(request)  # should not raise

    @pytest.mark.asyncio
    async def test_raises_429_when_lua_returns_one(self):
        """Lua script returns 1 → rate limited."""
        mock_redis = MagicMock()
        mock_redis.eval.return_value = 1

        limiter = RedisRateLimiter()
        request = _make_request("/api/v1/users", "GET")

        with patch("app.core.rate_limiter._redis_client", return_value=mock_redis):
            with pytest.raises(HTTPException) as exc_info:
                await limiter.check(request)

        assert exc_info.value.status_code == 429

    @pytest.mark.asyncio
    async def test_error_detail_includes_tier_and_limit(self):
        mock_redis = MagicMock()
        mock_redis.eval.return_value = 1

        limiter = RedisRateLimiter()
        request = _make_request("/api/v1/auth/token", "POST")

        with patch("app.core.rate_limiter._redis_client", return_value=mock_redis):
            with pytest.raises(HTTPException) as exc_info:
                await limiter.check(request)

        detail = exc_info.value.detail
        assert detail["code"] == "RATE_LIMIT"
        assert "auth" in detail["message"]
        assert "10" in detail["message"]  # auth limit
