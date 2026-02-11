import time
from fastapi import Request, HTTPException, status
from redis.asyncio import Redis
from app.config.settings import settings

class RedisRateLimiter:
    """
    A.17: Redis-backed sliding window rate limiter.
    Uses Lua script for atomic check-and-set operations.
    """
    def __init__(self, requests_per_minute: int = 60):
        self.rate = requests_per_minute
        self.window = 60
        self.redis = Redis.from_url(settings.REDIS_URL, encoding="utf-8", decode_responses=True)

    async def check(self, request: Request):
        client_ip = request.client.host if request.client else "unknown"
        key = f"rate_limit:{client_ip}"
        now = time.time()
        
        # Lua script to atomically remove old entries, count, and add new entry if allowed
        script = """
        local key = KEYS[1]
        local limit = tonumber(ARGV[1])
        local now = tonumber(ARGV[2])
        local window = tonumber(ARGV[3])
        local clear_before = now - window

        redis.call('ZREMRANGEBYSCORE', key, 0, clear_before)
        local count = redis.call('ZCARD', key)

        if count < limit then
            redis.call('ZADD', key, now, now)
            redis.call('EXPIRE', key, window)
            return 0
        else
            return 1
        end
        """
        
        result = await self.redis.eval(script, 1, key, self.rate, now, self.window)
        
        if result == 1:
            raise HTTPException(status_code=status.HTTP_429_TOO_MANY_REQUESTS, detail="Rate limit exceeded")