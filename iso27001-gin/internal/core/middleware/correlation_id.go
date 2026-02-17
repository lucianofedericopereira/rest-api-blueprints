package middleware

import (
	"fmt"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/google/uuid"
	"github.com/iso27001/gin-blueprint/internal/core/metrics"
	"github.com/iso27001/gin-blueprint/internal/infrastructure/telemetry"
)

// CorrelationID assigns a UUID request ID to every request (A.12).
// Preserves client-supplied X-Request-ID; generates one if absent.
// Injects the ID into the logger context and response headers.
func CorrelationID(budget *telemetry.ErrorBudgetTracker) gin.HandlerFunc {
	return func(c *gin.Context) {
		requestID := c.GetHeader("X-Request-ID")
		if requestID == "" {
			requestID = uuid.NewString()
		}
		c.Set("request_id", requestID)

		start := time.Now()

		c.Next()

		duration := time.Since(start)
		status := c.Writer.Status()

		// A.17: record in error budget (5xx consumes budget; 4xx tracked separately)
		budget.Record(status)

		// Prometheus
		metrics.RequestTotal.WithLabelValues(c.Request.Method, c.FullPath(), statusStr(status)).Inc()
		metrics.RequestDuration.WithLabelValues(c.Request.Method, c.FullPath()).Observe(duration.Seconds())
		if status >= 400 && status < 500 {
			metrics.ErrorsTotal.WithLabelValues("4xx").Inc()
		} else if status >= 500 {
			metrics.ErrorsTotal.WithLabelValues("5xx").Inc()
		}

		// Response headers (A.12: client-side tracing)
		c.Header("X-Request-ID", requestID)
		c.Header("X-Response-Time", duration.String())
	}
}

func statusStr(code int) string {
	return fmt.Sprintf("%d", code)
}
