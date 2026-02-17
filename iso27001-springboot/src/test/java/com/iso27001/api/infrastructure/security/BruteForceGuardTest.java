package com.iso27001.api.infrastructure.security;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.springframework.data.redis.connection.RedisConnectionFactory;
import org.springframework.data.redis.core.StringRedisTemplate;
import org.springframework.http.HttpStatus;
import org.springframework.web.server.ResponseStatusException;

import static org.assertj.core.api.Assertions.*;
import static org.mockito.Mockito.*;

/**
 * A.9 — Unit tests for BruteForceGuard (in-process fallback — no Redis).
 */
class BruteForceGuardTest {

    private BruteForceGuard guard;

    @BeforeEach
    void setUp() {
        // Mock Redis to always throw → triggers in-process fallback
        StringRedisTemplate redisTemplate = mock(StringRedisTemplate.class);
        RedisConnectionFactory factory = mock(RedisConnectionFactory.class);
        when(redisTemplate.getConnectionFactory()).thenReturn(factory);
        when(factory.getConnection()).thenThrow(new RuntimeException("Redis unavailable"));
        guard = new BruteForceGuard(redisTemplate);
    }

    @Test
    void allowsRequestWhenAccountIsClean() {
        assertThatCode(() -> guard.check("clean@example.com")).doesNotThrowAnyException();
    }

    @Test
    void doesNotLockAfterFewerThanFiveFailures() {
        for (int i = 0; i < 4; i++) guard.recordFailure("user@example.com");
        assertThatCode(() -> guard.check("user@example.com")).doesNotThrowAnyException();
    }

    @Test
    void locksAccountAfterFiveConsecutiveFailures() {
        for (int i = 0; i < 5; i++) guard.recordFailure("user@example.com");
        assertThatThrownBy(() -> guard.check("user@example.com"))
            .isInstanceOf(ResponseStatusException.class)
            .satisfies(e -> assertThat(((ResponseStatusException) e).getStatusCode())
                .isEqualTo(HttpStatus.TOO_MANY_REQUESTS));
    }

    @Test
    void differentIdentifiersAreIndependent() {
        for (int i = 0; i < 5; i++) guard.recordFailure("userA@example.com");
        assertThatCode(() -> guard.check("userB@example.com")).doesNotThrowAnyException();
    }

    @Test
    void clearUnlocksLockedAccount() {
        for (int i = 0; i < 5; i++) guard.recordFailure("user@example.com");
        guard.clear("user@example.com");
        assertThatCode(() -> guard.check("user@example.com")).doesNotThrowAnyException();
    }
}
