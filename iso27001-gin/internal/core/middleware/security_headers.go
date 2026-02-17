// Package middleware contains Gin middleware for security, telemetry, and rate limiting.
package middleware

import (
	"github.com/gin-gonic/gin"
)

// SecurityHeaders injects A.10-mandated HTTP security headers on every response.
// Mirrors FastAPI SecurityHeadersMiddleware and Symfony SecurityHeaderSubscriber.
func SecurityHeaders() gin.HandlerFunc {
	return func(c *gin.Context) {
		c.Header("Strict-Transport-Security", "max-age=31536000; includeSubDomains")
		c.Header("X-Frame-Options", "DENY")
		c.Header("X-Content-Type-Options", "nosniff")
		c.Header("Referrer-Policy", "strict-origin-when-cross-origin")
		c.Header("Content-Security-Policy", "default-src 'none'; frame-ancestors 'none'")
		c.Header("Permissions-Policy", "geolocation=(), microphone=(), camera=()")
		c.Header("Cross-Origin-Opener-Policy", "same-origin")
		c.Header("Cross-Origin-Embedder-Policy", "require-corp")
		// Remove server fingerprint headers
		c.Header("Server", "")
		c.Header("X-Powered-By", "")
		c.Next()
	}
}
