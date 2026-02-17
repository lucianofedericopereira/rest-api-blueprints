package com.iso27001.api.api.v1;

import com.iso27001.api.infrastructure.telemetry.ErrorBudgetTracker;
import com.iso27001.api.infrastructure.telemetry.QualityScoreCalculator;
import jakarta.persistence.EntityManager;
import jakarta.persistence.PersistenceContext;
import org.springframework.http.ResponseEntity;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;

import java.time.Instant;
import java.util.Map;

/**
 * A.17 — Health checks: liveness, readiness, detailed diagnostics.
 */
@RestController
@RequestMapping("/api/v1/health")
public class HealthController {

    private final ErrorBudgetTracker errorBudget;
    private final QualityScoreCalculator qualityScore;

    @PersistenceContext
    private EntityManager em;

    public HealthController(ErrorBudgetTracker errorBudget, QualityScoreCalculator qualityScore) {
        this.errorBudget = errorBudget;
        this.qualityScore = qualityScore;
    }

    /** Liveness — process is alive, no dependencies checked. */
    @GetMapping("/live")
    public ResponseEntity<Map<String, Object>> live() {
        return ResponseEntity.ok(Map.of("status", "ok", "timestamp", Instant.now().toString()));
    }

    /** Readiness — DB connectivity check. */
    @GetMapping("/ready")
    public ResponseEntity<Map<String, Object>> ready() {
        boolean dbOk = checkDb();
        String status = dbOk ? "ok" : "degraded";
        int httpStatus = dbOk ? 200 : 503;
        return ResponseEntity.status(httpStatus).body(Map.of(
            "status", status,
            "timestamp", Instant.now().toString(),
            "checks", Map.of("database", dbOk ? "ok" : "unavailable")
        ));
    }

    /** Detailed diagnostics — admin only. */
    @GetMapping("/detail")
    @PreAuthorize("hasRole('ADMIN')")
    public ResponseEntity<Map<String, Object>> detail() {
        ErrorBudgetTracker.Snapshot budget = errorBudget.snapshot();

        QualityScoreCalculator.Input input = new QualityScoreCalculator.Input(
            budget.totalRequests() - budget.failedRequests() - budget.clientErrors(),
            budget.totalRequests(),
            budget.totalRequests(),
            budget.totalRequests(),
            budget.observedAvailability(),
            1.0, 0.0, 0.0
        );
        QualityScoreCalculator.Result score = qualityScore.calculate(input);

        return ResponseEntity.ok(Map.of(
            "status", "ok",
            "timestamp", Instant.now().toString(),
            "java_version", System.getProperty("java.version"),
            "error_budget_remaining_pct", 100.0 - budget.budgetConsumedPct(),
            "budget_exhausted", budget.budgetExhausted(),
            "quality_score", score.composite(),
            "quality_gate_passed", score.passesGate()
        ));
    }

    private boolean checkDb() {
        try {
            em.createNativeQuery("SELECT 1").getSingleResult();
            return true;
        } catch (Exception e) {
            return false;
        }
    }
}
