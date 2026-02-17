package unit_test

import (
	"testing"

	"github.com/iso27001/gin-blueprint/internal/infrastructure/telemetry"
)

func TestQualityScore_PerfectScore(t *testing.T) {
	score := telemetry.Calculate(telemetry.QualityScoreInput{
		AuthChecksPassed:     100,
		AuthChecksTotal:      100,
		AuditEventsRecorded:  100,
		AuditEventsExpected:  100,
		Availability:         1.0,
		LogsWithCorrelID:     100,
		TotalLogs:            100,
		ObservedP95LatencyMS: 0,
		ObservedP99LatencyMS: 0,
	})

	composite := score.Composite()
	if composite < 0.99 {
		t.Errorf("perfect inputs should yield composite ≈ 1.0, got %f", composite)
	}
	if !score.PassesGate() {
		t.Error("perfect score should pass the production gate")
	}
}

func TestQualityScore_BelowGate(t *testing.T) {
	score := telemetry.Calculate(telemetry.QualityScoreInput{
		AuthChecksPassed:    0,
		AuthChecksTotal:     100,
		Availability:        0.5,
		ObservedP95LatencyMS: 1000,
	})

	if score.PassesGate() {
		t.Errorf("poor score (composite=%f) should fail the production gate", score.Composite())
	}
}

func TestSLOAlert_BreachDetection(t *testing.T) {
	// 5xx rate of 1% — exceeds 0.1% threshold
	alert := telemetry.EvaluateSLO(10, 0, 1000, 0, 0)
	if !alert.ErrorRateBreached {
		t.Error("expected error_rate_breached = true")
	}
	if !alert.AnyBreach() {
		t.Error("expected any_breach = true")
	}
}

func TestSLOAlert_NoBreachOnCleanSystem(t *testing.T) {
	alert := telemetry.EvaluateSLO(0, 0, 1000, 100, 200)
	if alert.AnyBreach() {
		t.Error("expected no breaches on clean system")
	}
}
