package com.iso27001.api.infrastructure.repositories;

import com.iso27001.api.domain.users.User;
import com.iso27001.api.domain.users.UserRepository;
import jakarta.persistence.EntityManager;
import jakarta.persistence.PersistenceContext;
import org.springframework.stereotype.Repository;
import org.springframework.transaction.annotation.Transactional;

import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;

/**
 * JPA implementation of the domain UserRepository interface.
 * Infrastructure adapter — must not leak into domain or api layers.
 */
@Repository
public class JpaUserRepository implements UserRepository {

    @PersistenceContext
    private EntityManager em;

    @Override
    public Optional<User> findById(UUID id) {
        return Optional.ofNullable(
            em.find(User.class, id)
        ).filter(u -> u.getDeletedAt() == null);
    }

    @Override
    public Optional<User> findByEmail(String email) {
        return em.createQuery(
                "SELECT u FROM User u WHERE u.email = :email AND u.deletedAt IS NULL", User.class)
            .setParameter("email", email)
            .getResultStream()
            .findFirst();
    }

    @Override
    public boolean existsByEmail(String email) {
        Long count = em.createQuery(
                "SELECT COUNT(u) FROM User u WHERE u.email = :email AND u.deletedAt IS NULL", Long.class)
            .setParameter("email", email)
            .getSingleResult();
        return count > 0;
    }

    @Override
    public List<User> findAll(int skip, int limit) {
        return em.createQuery(
                "SELECT u FROM User u WHERE u.deletedAt IS NULL ORDER BY u.createdAt DESC", User.class)
            .setFirstResult(skip)
            .setMaxResults(limit)
            .getResultList();
    }

    @Override
    public User save(User user) {
        if (user.getId() == null || em.find(User.class, user.getId()) == null) {
            em.persist(user);
            return user;
        }
        return em.merge(user);
    }

    @Override
    @Transactional
    public void softDelete(UUID id) {
        em.createQuery("UPDATE User u SET u.deletedAt = :now WHERE u.id = :id")
            .setParameter("now", Instant.now())
            .setParameter("id", id)
            .executeUpdate();
    }
}
