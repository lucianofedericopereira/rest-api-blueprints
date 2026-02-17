package com.iso27001.api.core.auth;

import io.jsonwebtoken.Claims;
import io.jsonwebtoken.Jwts;
import io.jsonwebtoken.security.Keys;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Service;

import javax.crypto.SecretKey;
import java.nio.charset.StandardCharsets;
import java.time.Duration;
import java.time.Instant;
import java.util.Date;
import java.util.UUID;

/**
 * A.9, A.10 — JWT signing and verification using HMAC-SHA-256.
 * Access tokens: 30 min | Refresh tokens: 7 days.
 */
@Service
public class JwtService {

    private final SecretKey signingKey;
    private final Duration accessExpiry;
    private final Duration refreshExpiry;

    public JwtService(
        @Value("${app.jwt.secret}") String secret,
        @Value("${app.jwt.access-expires-in:30m}") String accessExpiresIn,
        @Value("${app.jwt.refresh-expires-in:7d}") String refreshExpiresIn
    ) {
        byte[] keyBytes = secret.getBytes(StandardCharsets.UTF_8);
        if (keyBytes.length < 32) {
            throw new IllegalArgumentException("JWT_SECRET must be at least 32 bytes");
        }
        this.signingKey = Keys.hmacShaKeyFor(keyBytes);
        this.accessExpiry = parseDuration(accessExpiresIn);
        this.refreshExpiry = parseDuration(refreshExpiresIn);
    }

    public record TokenPair(String accessToken, String refreshToken) {}

    public TokenPair issueTokenPair(String userId, String role) {
        return new TokenPair(
            buildToken(userId, role, "access", accessExpiry),
            buildToken(userId, role, "refresh", refreshExpiry)
        );
    }

    /** Parses and validates a JWT; returns claims if valid. */
    public Claims parse(String token) {
        return Jwts.parser()
            .verifyWith(signingKey)
            .build()
            .parseSignedClaims(token)
            .getPayload();
    }

    private String buildToken(String userId, String role, String type, Duration expiry) {
        Instant now = Instant.now();
        return Jwts.builder()
            .subject(userId)
            .claim("role", role)
            .claim("type", type)
            .id(UUID.randomUUID().toString())
            .issuedAt(Date.from(now))
            .expiration(Date.from(now.plus(expiry)))
            .signWith(signingKey)
            .compact();
    }

    private static Duration parseDuration(String value) {
        value = value.trim().toLowerCase();
        if (value.endsWith("m")) return Duration.ofMinutes(Long.parseLong(value.replace("m", "")));
        if (value.endsWith("h")) return Duration.ofHours(Long.parseLong(value.replace("h", "")));
        if (value.endsWith("d")) return Duration.ofDays(Long.parseLong(value.replace("d", "")));
        return Duration.ofSeconds(Long.parseLong(value));
    }
}
