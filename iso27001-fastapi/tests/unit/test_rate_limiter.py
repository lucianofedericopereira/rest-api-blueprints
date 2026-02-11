"""Unit tests for the Redis rate limiter."""
import pytest
from unittest.mock import AsyncMock, MagicMock, patch

from app.core.rate_limiter import RedisRateLimiter


@pytest.fixture
def mock_redis():
    redis = AsyncMock()
    redis.execute_script = AsyncMock(return_value=[5, 1])
    return redis


class TestRedisRateLimiter:
    def test_init(self):
        with patch("app.core.rate_limiter.redis.Redis"):
            limiter = RedisRateLimiter(redis_url="redis://localhost:6379", limit=100, window=60)
            assert limiter.limit == 100
            assert limiter.window == 60

    @pytest.mark.asyncio
    async def test_is_allowed_returns_true_when_under_limit(self):
        with patch("app.core.rate_limiter.redis.Redis") as mock_redis_cls:
            mock_client = AsyncMock()
            # Lua script returns [remaining_tokens, allowed=1]
            mock_client.eval = AsyncMock(return_value=[95, 1])
            mock_redis_cls.return_value = mock_client

            limiter = RedisRateLimiter(redis_url="redis://localhost:6379", limit=100, window=60)
            result = await limiter.is_allowed("test_key")
            assert result is True

    @pytest.mark.asyncio
    async def test_is_allowed_returns_false_when_over_limit(self):
        with patch("app.core.rate_limiter.redis.Redis") as mock_redis_cls:
            mock_client = AsyncMock()
            # Lua script returns [0, 0] when rate-limited
            mock_client.eval = AsyncMock(return_value=[0, 0])
            mock_redis_cls.return_value = mock_client

            limiter = RedisRateLimiter(redis_url="redis://localhost:6379", limit=100, window=60)
            result = await limiter.is_allowed("test_key")
            assert result is False
