package com.iso27001.api.domain.users;

import java.util.List;
import java.util.Optional;
import java.util.UUID;

/**
 * A.9 — Domain repository interface. No JPA/Spring imports allowed here (domain purity).
 * Implementations live in infrastructure.repositories.
 */
public interface UserRepository {
    Optional<User> findById(UUID id);
    Optional<User> findByEmail(String email);
    boolean existsByEmail(String email);
    List<User> findAll(int skip, int limit);
    User save(User user);
    void softDelete(UUID id);
}
