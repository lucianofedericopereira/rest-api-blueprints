package middleware

import (
	"net/http"
	"strings"

	"github.com/gin-gonic/gin"
	"github.com/iso27001/gin-blueprint/internal/core/auth"
)

// JWTAuth validates Bearer tokens and injects the claims into the Gin context.
// Routes that don't require auth should skip this middleware or use it
// with the RequireRole helper which checks for the "public" context key.
func JWTAuth(secret string) gin.HandlerFunc {
	return func(c *gin.Context) {
		header := c.GetHeader("Authorization")
		if !strings.HasPrefix(header, "Bearer ") {
			c.AbortWithStatusJSON(http.StatusUnauthorized, gin.H{
				"error": gin.H{"code": "UNAUTHORIZED", "message": "Missing or invalid authorization token"},
			})
			return
		}
		tokenStr := strings.TrimPrefix(header, "Bearer ")
		claims, err := auth.Verify(tokenStr, secret)
		if err != nil {
			c.AbortWithStatusJSON(http.StatusUnauthorized, gin.H{
				"error": gin.H{"code": "UNAUTHORIZED", "message": "Invalid or expired token"},
			})
			return
		}
		c.Set("user_id", claims.Subject)
		c.Set("user_role", claims.Role)
		c.Next()
	}
}

// RequireRole aborts with 403 if the authenticated user's role is insufficient.
// Must be used after JWTAuth in the handler chain.
func RequireRole(role string) gin.HandlerFunc {
	hierarchy := map[string]int{"viewer": 1, "analyst": 2, "manager": 3, "admin": 4}
	return func(c *gin.Context) {
		userRole, _ := c.Get("user_role")
		r, _ := userRole.(string)
		if hierarchy[r] < hierarchy[role] {
			c.AbortWithStatusJSON(http.StatusForbidden, gin.H{
				"error": gin.H{"code": "FORBIDDEN", "message": "Insufficient role"},
			})
			return
		}
		c.Next()
	}
}
