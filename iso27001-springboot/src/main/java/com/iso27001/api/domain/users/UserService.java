package com.iso27001.api.domain.users;

import com.iso27001.api.domain.users.events.UserCreatedEvent;
import org.springframework.context.ApplicationEventPublisher;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.util.HexFormat;
import java.util.List;
import java.util.Optional;
import java.util.UUID;

/**
 * A.9, A.10 — Core user domain service.
 * - Passwords hashed with bcrypt cost 12 (A.10)
 * - Domain events emitted with email hash, never raw PII (A.12)
 */
@Service
public class UserService {

    private final UserRepository repo;
    private final PasswordEncoder passwordEncoder;
    private final ApplicationEventPublisher events;

    public UserService(UserRepository repo, PasswordEncoder passwordEncoder, ApplicationEventPublisher events) {
        this.repo = repo;
        this.passwordEncoder = passwordEncoder;
        this.events = events;
    }

    @Transactional
    public User create(String email, String password, String fullName, UserRole role) {
        if (repo.existsByEmail(email)) {
            throw new IllegalArgumentException("Email already registered");
        }
        User user = new User();
        user.setEmail(email);
        user.setHashedPassword(passwordEncoder.encode(password));
        user.setFullName(fullName);
        user.setRole(role != null ? role : UserRole.VIEWER);
        user.setActive(true);

        User saved = repo.save(user);

        // A.12 — emit domain event with hashed email, never raw
        String emailHash = sha256(saved.getEmail());
        events.publishEvent(new UserCreatedEvent(saved.getId().toString(), emailHash, saved.getRole().name()));

        return saved;
    }

    public boolean verifyPassword(String plaintext, String hash) {
        // A.10 — constant-time comparison via bcrypt
        return passwordEncoder.matches(plaintext, hash);
    }

    @Transactional(readOnly = true)
    public Optional<User> findById(UUID id) {
        return repo.findById(id);
    }

    @Transactional(readOnly = true)
    public Optional<User> findByEmail(String email) {
        return repo.findByEmail(email);
    }

    @Transactional(readOnly = true)
    public List<User> findAll(int skip, int limit) {
        return repo.findAll(skip, limit);
    }

    @Transactional
    public User update(User user) {
        return repo.save(user);
    }

    @Transactional
    public void softDelete(UUID id) {
        repo.softDelete(id);
    }

    private static String sha256(String input) {
        try {
            byte[] digest = MessageDigest.getInstance("SHA-256")
                .digest(input.getBytes(StandardCharsets.UTF_8));
            return HexFormat.of().formatHex(digest);
        } catch (NoSuchAlgorithmException e) {
            throw new IllegalStateException("SHA-256 not available", e);
        }
    }
}
