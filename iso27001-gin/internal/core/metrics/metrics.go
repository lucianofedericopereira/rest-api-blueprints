// Package metrics exposes Prometheus instrumentation (A.17).
package metrics

import (
	"github.com/prometheus/client_golang/prometheus"
	"github.com/prometheus/client_golang/prometheus/promauto"
)

// A.17: SLO alert thresholds — defined once, referenced everywhere.
const (
	SLOP95LatencyMS float64 = 200.0 // alert if P95 exceeds 200 ms
	SLOP99LatencyMS float64 = 500.0 // alert if P99 exceeds 500 ms
	SLOErrorRatePct float64 = 0.1   // alert if 5xx error rate (%) exceeds 0.1%
)

var (
	RequestTotal = promauto.NewCounterVec(prometheus.CounterOpts{
		Name: "http_requests_total",
		Help: "Total HTTP requests",
	}, []string{"method", "endpoint", "status_code"})

	RequestDuration = promauto.NewHistogramVec(prometheus.HistogramOpts{
		Name:    "http_request_duration_seconds",
		Help:    "HTTP request latency",
		Buckets: []float64{0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5},
	}, []string{"method", "endpoint"})

	// A.17: Separate 4xx (client errors) from 5xx (server errors) for accurate alerting.
	ErrorsTotal = promauto.NewCounterVec(prometheus.CounterOpts{
		Name: "http_errors_total",
		Help: "HTTP error responses split by error class (4xx vs 5xx)",
	}, []string{"error_class"})
)
