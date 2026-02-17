package telemetry

// QualityScore holds pillar scores aligned to ISO 27001 (A.17).
//
// Pillar         Weight   ISO 27001
// ─────────────────────────────────────
// Security        40%     A.9, A.10
// DataIntegrity   20%     A.12
// Reliability     15%     A.17
// Auditability    15%     A.12
// Performance      5%     A.17
// (gap)            5%     reserved
//
// Score of 1.0 = perfect; 0.0 = complete failure.
// A score below 0.70 should block production deployments.

const (
	ProductionGate    = 0.70
	SLOP95LatencyMS   = 200.0
	SLOP99LatencyMS   = 500.0
	SLOErrorRatePct   = 0.1
	ClientErrSpikePct = 5.0
)

// QualityScore holds the five pillar scores.
type QualityScore struct {
	Security      float64
	DataIntegrity float64
	Reliability   float64
	Auditability  float64
	Performance   float64
}

// Composite returns the weighted composite score normalised to [0,1].
func (q QualityScore) Composite() float64 {
	const weightSum = 0.95 // 5% gap reserved
	raw := q.Security*0.40 + q.DataIntegrity*0.20 + q.Reliability*0.15 +
		q.Auditability*0.15 + q.Performance*0.05
	return raw / weightSum
}

// PassesGate reports whether this score meets the production deployment gate.
func (q QualityScore) PassesGate() bool { return q.Composite() >= ProductionGate }

// SLOAlert holds boolean breach signals for each SLO threshold.
type SLOAlert struct {
	P95LatencyBreached  bool
	P99LatencyBreached  bool
	ErrorRateBreached   bool
	ClientErrorSpike    bool
}

// AnyBreach returns true if at least one SLO is violated.
func (a SLOAlert) AnyBreach() bool {
	return a.P95LatencyBreached || a.P99LatencyBreached ||
		a.ErrorRateBreached || a.ClientErrorSpike
}

// QualityScoreInput bundles the runtime signals needed to compute a score.
type QualityScoreInput struct {
	AuthChecksPassed     int
	AuthChecksTotal      int
	AuditEventsRecorded  int
	AuditEventsExpected  int
	Availability         float64 // from ErrorBudgetSnapshot
	LogsWithCorrelID     int
	TotalLogs            int
	ObservedP95LatencyMS float64
	ObservedP99LatencyMS float64
}

// Calculate computes pillar scores from live runtime signals.
func Calculate(in QualityScoreInput) QualityScore {
	return QualityScore{
		Security:      ratio(in.AuthChecksPassed, in.AuthChecksTotal),
		DataIntegrity: ratio(in.AuditEventsRecorded, in.AuditEventsExpected),
		Reliability:   clamp01(in.Availability),
		Auditability:  ratio(in.LogsWithCorrelID, in.TotalLogs),
		Performance:   latencyScore(in.ObservedP95LatencyMS, 500.0),
	}
}

// EvaluateSLO checks which SLO thresholds are breached.
func EvaluateSLO(failed, clientErrors, total int64, p95MS, p99MS float64) SLOAlert {
	var errRatePct, ceRatePct float64
	if total > 0 {
		errRatePct = float64(failed) / float64(total) * 100.0
		ceRatePct = float64(clientErrors) / float64(total) * 100.0
	}
	return SLOAlert{
		P95LatencyBreached: p95MS > SLOP95LatencyMS,
		P99LatencyBreached: p99MS > SLOP99LatencyMS,
		ErrorRateBreached:  errRatePct > SLOErrorRatePct,
		ClientErrorSpike:   ceRatePct > ClientErrSpikePct,
	}
}

func ratio(num, den int) float64 {
	if den <= 0 {
		return 1.0 // no data → assume perfect at startup
	}
	return clamp01(float64(num) / float64(den))
}

func clamp01(v float64) float64 {
	if v < 0 {
		return 0
	}
	if v > 1 {
		return 1
	}
	return v
}

func latencyScore(observedMS, targetMS float64) float64 {
	if targetMS <= 0 {
		return 1.0
	}
	return clamp01(1.0 - observedMS/targetMS)
}
