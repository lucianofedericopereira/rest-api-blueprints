package com.iso27001.api.domain.users.events;

/**
 * A.12 — Domain event emitted after user creation.
 * Contains only hashed email — raw PII is never included in events.
 */
public record UserCreatedEvent(
    String userId,
    String emailHash,   // SHA-256 of email — never raw email
    String role
) {}
