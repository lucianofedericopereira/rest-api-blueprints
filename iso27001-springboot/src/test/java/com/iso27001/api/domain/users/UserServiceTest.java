package com.iso27001.api.domain.users;

import com.iso27001.api.domain.users.events.UserCreatedEvent;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.springframework.context.ApplicationEventPublisher;
import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;

import java.util.ArrayList;
import java.util.List;
import java.util.Optional;
import java.util.UUID;

import static org.assertj.core.api.Assertions.*;

/**
 * A.10 — Unit tests for UserService: bcrypt hashing, domain events, conflict detection.
 */
class UserServiceTest {

    private InMemoryUserRepository repo;
    private UserService service;
    private final List<UserCreatedEvent> emittedEvents = new ArrayList<>();

    @BeforeEach
    void setUp() {
        repo = new InMemoryUserRepository();
        ApplicationEventPublisher publisher = event -> {
            if (event instanceof UserCreatedEvent e) emittedEvents.add(e);
        };
        service = new UserService(repo, new BCryptPasswordEncoder(4), publisher);
    }

    @Test
    void createHashesPasswordWithBcrypt() {
        User user = service.create("alice@example.com", "StrongPass123!", null, UserRole.VIEWER);
        assertThat(user.getHashedPassword()).startsWith("$2a$");
        assertThat(user.getHashedPassword()).isNotEqualTo("StrongPass123!");
    }

    @Test
    void verifyPasswordReturnsTrueForCorrectPassword() {
        User user = service.create("bob@example.com", "MyPassword123!", null, UserRole.VIEWER);
        assertThat(service.verifyPassword("MyPassword123!", user.getHashedPassword())).isTrue();
    }

    @Test
    void verifyPasswordReturnsFalseForWrongPassword() {
        User user = service.create("carol@example.com", "Correct123!", null, UserRole.VIEWER);
        assertThat(service.verifyPassword("WrongPassword!", user.getHashedPassword())).isFalse();
    }

    @Test
    void createEmitsDomainEventWithHashedEmail() {
        service.create("dave@example.com", "Secret12345!", null, UserRole.VIEWER);
        assertThat(emittedEvents).hasSize(1);
        UserCreatedEvent event = emittedEvents.get(0);
        assertThat(event.emailHash()).isNotEqualTo("dave@example.com");
        assertThat(event.emailHash()).hasSize(64); // SHA-256 hex
    }

    @Test
    void createThrowsOnDuplicateEmail() {
        service.create("eve@example.com", "Password123!", null, UserRole.VIEWER);
        assertThatThrownBy(() -> service.create("eve@example.com", "Other123!", null, UserRole.VIEWER))
            .isInstanceOf(IllegalArgumentException.class)
            .hasMessageContaining("already registered");
    }

    @Test
    void defaultRoleIsViewer() {
        User user = service.create("frank@example.com", "Password123!", null, null);
        assertThat(user.getRole()).isEqualTo(UserRole.VIEWER);
    }

    // ── Minimal in-memory repo for unit tests ────────────────────────────────

    static class InMemoryUserRepository implements UserRepository {
        private final List<User> store = new ArrayList<>();

        @Override
        public Optional<User> findById(UUID id) {
            return store.stream().filter(u -> u.getId().equals(id) && u.getDeletedAt() == null).findFirst();
        }

        @Override
        public Optional<User> findByEmail(String email) {
            return store.stream().filter(u -> u.getEmail().equals(email) && u.getDeletedAt() == null).findFirst();
        }

        @Override
        public boolean existsByEmail(String email) {
            return store.stream().anyMatch(u -> u.getEmail().equals(email) && u.getDeletedAt() == null);
        }

        @Override
        public List<User> findAll(int skip, int limit) {
            return store.stream().filter(u -> u.getDeletedAt() == null).skip(skip).limit(limit).toList();
        }

        @Override
        public User save(User user) {
            // Simulate JPA @PrePersist: assign a UUID when none is set yet.
            if (user.getId() == null) {
                try {
                    var field = User.class.getDeclaredField("id");
                    field.setAccessible(true);
                    field.set(user, UUID.randomUUID());
                } catch (NoSuchFieldException | IllegalAccessException e) {
                    throw new RuntimeException(e);
                }
            }
            store.add(user);
            return user;
        }

        @Override
        public void softDelete(UUID id) {
            store.stream().filter(u -> u.getId().equals(id))
                .forEach(u -> u.setDeletedAt(java.time.Instant.now()));
        }
    }
}
