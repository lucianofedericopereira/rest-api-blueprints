// Package telemetry provides the A.17 error budget tracker and quality score calculator.
package telemetry

import (
	"math"
	"sync/atomic"
)

// ErrorBudgetSnapshot is a point-in-time snapshot of SLA health.
type ErrorBudgetSnapshot struct {
	SLATarget            float64
	TotalRequests        int64
	FailedRequests       int64  // 5xx — consumes budget
	ClientErrors         int64  // 4xx — tracked separately
	ObservedAvailability float64
	BudgetConsumedPct    float64
	BudgetExhausted      bool
}

// ErrorBudgetTracker counts requests and computes the SLA error budget (A.17).
// Uses atomic counters for goroutine safety without a mutex.
type ErrorBudgetTracker struct {
	slaTarget    float64
	total        atomic.Int64
	failed       atomic.Int64
	clientErrors atomic.Int64
}

// NewErrorBudgetTracker creates a tracker with the given SLA target (e.g. 0.999).
func NewErrorBudgetTracker(slaTarget float64) *ErrorBudgetTracker {
	return &ErrorBudgetTracker{slaTarget: slaTarget}
}

// Record classifies a response status code into the appropriate counter.
func (t *ErrorBudgetTracker) Record(statusCode int) {
	t.total.Add(1)
	switch {
	case statusCode >= 500:
		t.failed.Add(1)
	case statusCode >= 400:
		t.clientErrors.Add(1)
	}
}

// Snapshot returns current error budget state.
func (t *ErrorBudgetTracker) Snapshot() ErrorBudgetSnapshot {
	total := t.total.Load()
	failed := t.failed.Load()
	ce := t.clientErrors.Load()

	if total == 0 {
		return ErrorBudgetSnapshot{SLATarget: t.slaTarget, ObservedAvailability: 1.0}
	}

	availability := float64(total-failed) / float64(total)
	allowedRate := 1.0 - t.slaTarget
	var consumed float64
	if allowedRate > 0 {
		raw := float64(failed) / float64(total) / allowedRate * 100.0
		consumed = math.Round(raw*1000) / 1000
		if consumed > 100.0 {
			consumed = 100.0
		}
	} else if failed > 0 {
		consumed = 100.0
	}

	return ErrorBudgetSnapshot{
		SLATarget:            t.slaTarget,
		TotalRequests:        total,
		FailedRequests:       failed,
		ClientErrors:         ce,
		ObservedAvailability: math.Round(availability*1e6) / 1e6,
		BudgetConsumedPct:    consumed,
		BudgetExhausted:      consumed >= 100.0,
	}
}

// Reset clears all counters (call at the start of a new SLA window).
func (t *ErrorBudgetTracker) Reset() {
	t.total.Store(0)
	t.failed.Store(0)
	t.clientErrors.Store(0)
}
