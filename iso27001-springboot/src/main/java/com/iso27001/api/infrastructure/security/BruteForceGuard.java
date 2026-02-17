package com.iso27001.api.infrastructure.security;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.data.redis.core.StringRedisTemplate;
import org.springframework.http.HttpStatus;
import org.springframework.stereotype.Component;
import org.springframework.web.server.ResponseStatusException;

import java.time.Duration;
import java.util.Map;
import java.util.concurrent.ConcurrentHashMap;

/**
 * A.9 — Brute-force lockout (5 failures → 15 min lock).
 * Redis-primary with in-process ConcurrentHashMap fallback (A.17).
 */
@Component
public class BruteForceGuard {

    private static final Logger log = LoggerFactory.getLogger(BruteForceGuard.class);
    private static final int MAX_ATTEMPTS = 5;
    private static final Duration LOCKOUT_TTL = Duration.ofMinutes(15);
    private static final String KEY_PREFIX = "bf:";

    private final StringRedisTemplate redis;

    // In-process fallback for when Redis is unavailable
    private record LocalEntry(int count, long lockedUntilMs) {}
    private final Map<String, LocalEntry> local = new ConcurrentHashMap<>();

    public BruteForceGuard(StringRedisTemplate redis) {
        this.redis = redis;
    }

    /** Throws 429 if the identifier is currently locked. */
    public void check(String identifier) {
        if (isRedisAvailable()) {
            String lockKey = KEY_PREFIX + "lock:" + identifier;
            if (Boolean.TRUE.equals(redis.hasKey(lockKey))) {
                throwLocked();
            }
        } else {
            LocalEntry entry = local.get(identifier);
            if (entry != null && entry.lockedUntilMs() > System.currentTimeMillis()) {
                throwLocked();
            }
        }
    }

    /** Increments failure counter; locks account on threshold. */
    public void recordFailure(String identifier) {
        if (isRedisAvailable()) {
            String countKey = KEY_PREFIX + "count:" + identifier;
            Long count = redis.opsForValue().increment(countKey);
            if (count != null && count == 1) {
                redis.expire(countKey, LOCKOUT_TTL);
            }
            if (count != null && count >= MAX_ATTEMPTS) {
                String lockKey = KEY_PREFIX + "lock:" + identifier;
                redis.opsForValue().set(lockKey, "1", LOCKOUT_TTL);
                log.warn("{\"event\":\"account_locked\",\"identifier_hash\":\"{}\"}", identifier.hashCode());
            }
        } else {
            local.compute(identifier, (k, entry) -> {
                int newCount = (entry == null ? 0 : entry.count()) + 1;
                long lockedUntil = newCount >= MAX_ATTEMPTS
                    ? System.currentTimeMillis() + LOCKOUT_TTL.toMillis()
                    : (entry != null ? entry.lockedUntilMs() : 0);
                return new LocalEntry(newCount, lockedUntil);
            });
        }
    }

    /** Clears failure counter on successful login. */
    public void clear(String identifier) {
        if (isRedisAvailable()) {
            redis.delete(KEY_PREFIX + "count:" + identifier);
            redis.delete(KEY_PREFIX + "lock:" + identifier);
        } else {
            local.remove(identifier);
        }
    }

    private boolean isRedisAvailable() {
        try {
            redis.getConnectionFactory().getConnection().ping();
            return true;
        } catch (Exception ignored) {
            return false;
        }
    }

    private void throwLocked() {
        throw new ResponseStatusException(
            HttpStatus.TOO_MANY_REQUESTS,
            "{\"code\":\"ACCOUNT_LOCKED\",\"message\":\"Account temporarily locked due to multiple failed login attempts\"}"
        );
    }
}
