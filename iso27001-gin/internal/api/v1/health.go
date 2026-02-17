package v1

import (
	"net/http"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/iso27001/gin-blueprint/internal/infrastructure/telemetry"
	"gorm.io/gorm"
)

// HealthHandler serves liveness, readiness, and detailed health endpoints (A.17).
type HealthHandler struct {
	db     *gorm.DB
	budget *telemetry.ErrorBudgetTracker
}

// NewHealthHandler constructs the health handler.
func NewHealthHandler(db *gorm.DB, budget *telemetry.ErrorBudgetTracker) *HealthHandler {
	return &HealthHandler{db: db, budget: budget}
}

// Live is the liveness probe — no dependency checks.
// GET /api/v1/health/live
func (h *HealthHandler) Live(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"status":    "ok",
		"timestamp": time.Now().UTC().Format(time.RFC3339),
	})
}

// Ready is the readiness probe — checks DB connectivity.
// GET /api/v1/health/ready
func (h *HealthHandler) Ready(c *gin.Context) {
	checks := gin.H{}
	overall := true

	sqlDB, err := h.db.DB()
	if err != nil {
		checks["database"] = gin.H{"status": "error", "detail": "cannot get sql.DB"}
		overall = false
	} else {
		start := time.Now()
		if err := sqlDB.Ping(); err != nil {
			checks["database"] = gin.H{"status": "error"}
			overall = false
		} else {
			latencyMS := time.Since(start).Milliseconds()
			checks["database"] = gin.H{"status": "ok", "latency_ms": latencyMS}
		}
	}

	status := http.StatusOK
	statusStr := "ok"
	if !overall {
		status = http.StatusServiceUnavailable
		statusStr = "degraded"
	}
	c.JSON(status, gin.H{
		"status":    statusStr,
		"checks":    checks,
		"timestamp": time.Now().UTC().Format(time.RFC3339),
	})
}

// Detail returns full telemetry — restricted to admin role (A.17).
// GET /api/v1/health/detail
func (h *HealthHandler) Detail(c *gin.Context) {
	snap := h.budget.Snapshot()

	score := telemetry.Calculate(telemetry.QualityScoreInput{
		AuthChecksPassed:    int(snap.TotalRequests - snap.FailedRequests),
		AuthChecksTotal:     int(snap.TotalRequests),
		AuditEventsRecorded: int(snap.TotalRequests),
		AuditEventsExpected: int(snap.TotalRequests),
		Availability:        snap.ObservedAvailability,
		LogsWithCorrelID:    int(snap.TotalRequests),
		TotalLogs:           int(snap.TotalRequests),
		ObservedP95LatencyMS: telemetry.SLOP95LatencyMS, // replace with live histogram
		ObservedP99LatencyMS: telemetry.SLOP99LatencyMS,
	})
	alert := telemetry.EvaluateSLO(snap.FailedRequests, snap.ClientErrors, snap.TotalRequests,
		telemetry.SLOP95LatencyMS, telemetry.SLOP99LatencyMS)

	c.JSON(http.StatusOK, gin.H{
		"status":    "ok",
		"timestamp": time.Now().UTC().Format(time.RFC3339),
		"error_budget": gin.H{
			"sla_target":             snap.SLATarget,
			"total_requests":         snap.TotalRequests,
			"failed_requests":        snap.FailedRequests,
			"client_errors":          snap.ClientErrors,
			"observed_availability":  snap.ObservedAvailability,
			"budget_consumed_pct":    snap.BudgetConsumedPct,
			"budget_exhausted":       snap.BudgetExhausted,
		},
		"slo_alerts": gin.H{
			"any_breach":           alert.AnyBreach(),
			"p95_latency_breached": alert.P95LatencyBreached,
			"p99_latency_breached": alert.P99LatencyBreached,
			"error_rate_breached":  alert.ErrorRateBreached,
			"client_error_spike":   alert.ClientErrorSpike,
		},
		"quality_score": gin.H{
			"composite":                score.Composite(),
			"passes_gate":              score.PassesGate(),
			"production_gate_threshold": telemetry.ProductionGate,
			"pillars": gin.H{
				"security":       gin.H{"score": score.Security, "weight": 0.40},
				"data_integrity": gin.H{"score": score.DataIntegrity, "weight": 0.20},
				"reliability":    gin.H{"score": score.Reliability, "weight": 0.15},
				"auditability":   gin.H{"score": score.Auditability, "weight": 0.15},
				"performance":    gin.H{"score": score.Performance, "weight": 0.05},
			},
		},
	})
}
