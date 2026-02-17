package unit_test

import (
	"testing"

	"github.com/iso27001/gin-blueprint/internal/infrastructure/telemetry"
)

func TestErrorBudgetTracker_InitialState(t *testing.T) {
	tracker := telemetry.NewErrorBudgetTracker(0.999)
	snap := tracker.Snapshot()

	if snap.TotalRequests != 0 {
		t.Errorf("expected 0 total, got %d", snap.TotalRequests)
	}
	if snap.ObservedAvailability != 1.0 {
		t.Errorf("expected availability 1.0, got %f", snap.ObservedAvailability)
	}
	if snap.BudgetConsumedPct != 0.0 {
		t.Errorf("expected 0%% consumed, got %f", snap.BudgetConsumedPct)
	}
}

func TestErrorBudgetTracker_5xxConsumesBudget(t *testing.T) {
	tracker := telemetry.NewErrorBudgetTracker(0.999)
	for i := 0; i < 999; i++ {
		tracker.Record(200)
	}
	tracker.Record(500)
	snap := tracker.Snapshot()

	if snap.TotalRequests != 1000 {
		t.Errorf("expected 1000 total, got %d", snap.TotalRequests)
	}
	if snap.FailedRequests != 1 {
		t.Errorf("expected 1 failed, got %d", snap.FailedRequests)
	}
	// 1 failure out of 1000 = 0.1% = exactly the 99.9% budget
	if snap.BudgetConsumedPct < 99.0 || snap.BudgetConsumedPct > 101.0 {
		t.Errorf("expected ~100%% consumed, got %f", snap.BudgetConsumedPct)
	}
}

func TestErrorBudgetTracker_4xxDoesNotConsumeBudget(t *testing.T) {
	tracker := telemetry.NewErrorBudgetTracker(0.999)
	for i := 0; i < 100; i++ {
		tracker.Record(400)
	}
	snap := tracker.Snapshot()

	if snap.FailedRequests != 0 {
		t.Errorf("expected 0 failures (4xx doesn't consume budget), got %d", snap.FailedRequests)
	}
	if snap.ClientErrors != 100 {
		t.Errorf("expected 100 client errors, got %d", snap.ClientErrors)
	}
	if snap.BudgetConsumedPct != 0 {
		t.Errorf("expected 0%% budget consumed, got %f", snap.BudgetConsumedPct)
	}
}

func TestErrorBudgetTracker_Reset(t *testing.T) {
	tracker := telemetry.NewErrorBudgetTracker(0.999)
	tracker.Record(500)
	tracker.Reset()
	snap := tracker.Snapshot()

	if snap.TotalRequests != 0 {
		t.Errorf("expected 0 after reset, got %d", snap.TotalRequests)
	}
}
