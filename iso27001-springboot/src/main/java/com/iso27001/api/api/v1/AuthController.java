package com.iso27001.api.api.v1;

import com.iso27001.api.core.auth.JwtService;
import com.iso27001.api.domain.users.User;
import com.iso27001.api.domain.users.UserService;
import com.iso27001.api.infrastructure.security.BruteForceGuard;
import io.jsonwebtoken.Claims;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Email;
import jakarta.validation.constraints.NotBlank;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.Map;
import java.util.Optional;

/**
 * A.9 — Authentication endpoints: login, refresh, logout.
 */
@RestController
@RequestMapping("/api/v1/auth")
public class AuthController {

    private final UserService userService;
    private final JwtService jwtService;
    private final BruteForceGuard bruteForce;

    public AuthController(UserService userService, JwtService jwtService, BruteForceGuard bruteForce) {
        this.userService = userService;
        this.jwtService = jwtService;
        this.bruteForce = bruteForce;
    }

    record LoginRequest(@Email @NotBlank String email, @NotBlank String password) {}
    record RefreshRequest(@NotBlank String refresh_token) {}
    record TokenPairResponse(String access_token, String refresh_token, String token_type) {}

    @PostMapping("/login")
    public ResponseEntity<TokenPairResponse> login(@Valid @RequestBody LoginRequest req) {
        // A.9 — brute-force check before any DB query
        bruteForce.check(req.email());

        Optional<User> userOpt = userService.findByEmail(req.email());
        if (userOpt.isEmpty() || !userService.verifyPassword(req.password(), userOpt.get().getHashedPassword())) {
            bruteForce.recordFailure(req.email());
            return ResponseEntity.status(401)
                .body(null);
        }

        User user = userOpt.get();
        if (!user.isActive()) {
            return ResponseEntity.status(401).body(null);
        }

        bruteForce.clear(req.email());
        JwtService.TokenPair pair = jwtService.issueTokenPair(user.getId().toString(), user.getRole().name());
        return ResponseEntity.ok(new TokenPairResponse(pair.accessToken(), pair.refreshToken(), "bearer"));
    }

    @PostMapping("/refresh")
    public ResponseEntity<TokenPairResponse> refresh(@Valid @RequestBody RefreshRequest req) {
        Claims claims;
        try {
            claims = jwtService.parse(req.refresh_token());
        } catch (Exception e) {
            return ResponseEntity.status(401).body(null);
        }

        if (!"refresh".equals(claims.get("type", String.class))) {
            return ResponseEntity.status(401).body(null);
        }

        Optional<User> userOpt = userService.findByEmail(claims.getSubject());
        // findById by subject (UUID)
        Optional<User> user = userOpt.isPresent() ? userOpt
            : userService.findById(java.util.UUID.fromString(claims.getSubject()));
        if (user.isEmpty() || !user.get().isActive()) {
            return ResponseEntity.status(401).body(null);
        }

        JwtService.TokenPair pair = jwtService.issueTokenPair(
            user.get().getId().toString(), user.get().getRole().name());
        return ResponseEntity.ok(new TokenPairResponse(pair.accessToken(), pair.refreshToken(), "bearer"));
    }

    @PostMapping("/logout")
    public ResponseEntity<Map<String, String>> logout() {
        // A.9 — stateless JWT: client discards tokens
        return ResponseEntity.ok(Map.of("message", "Logged out successfully"));
    }
}
