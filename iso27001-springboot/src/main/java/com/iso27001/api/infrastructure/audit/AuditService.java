package com.iso27001.api.infrastructure.audit;

import com.iso27001.api.domain.users.events.UserCreatedEvent;
import jakarta.persistence.EntityManager;
import jakarta.persistence.PersistenceContext;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.context.event.EventListener;
import org.springframework.scheduling.annotation.Async;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Propagation;
import org.springframework.transaction.annotation.Transactional;

import java.util.Map;

/**
 * A.12 — Appends immutable audit records. Best-effort (never crashes request).
 * Listens for domain events via Spring's ApplicationEventPublisher.
 */
@Service
public class AuditService {

    private static final Logger log = LoggerFactory.getLogger(AuditService.class);

    @PersistenceContext
    private EntityManager em;

    @Transactional(propagation = Propagation.REQUIRES_NEW)
    public void record(String action, String performedBy, String resourceType,
                       String resourceId, Map<String, Object> changes,
                       String ipAddress, String correlationId) {
        try {
            AuditLog entry = new AuditLog();
            entry.setAction(action);
            entry.setPerformedBy(performedBy != null ? performedBy : "system");
            entry.setResourceType(resourceType);
            entry.setResourceId(resourceId);
            entry.setChanges(changes);
            entry.setIpAddress(ipAddress);
            entry.setCorrelationId(correlationId);
            em.persist(entry);
        } catch (Exception e) {
            // A.12 — best-effort, log and continue
            log.error("{\"event\":\"audit_write_failed\",\"action\":\"{}\",\"error\":\"{}\"}", action, e.getMessage());
        }
    }

    @Async
    @EventListener
    public void onUserCreated(UserCreatedEvent event) {
        record(
            "user.created",
            "system",
            "user",
            event.userId(),
            Map.of("email_hash", event.emailHash(), "role", event.role()),
            null,
            null
        );
    }
}
