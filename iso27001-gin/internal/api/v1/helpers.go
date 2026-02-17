package v1

import (
	"github.com/gin-gonic/gin"
	"github.com/google/uuid"
)

// errorResp builds the standard error response shape (A.14).
// Never exposes internal details or stack traces.
func errorResp(code, message string) gin.H {
	return gin.H{"error": gin.H{"code": code, "message": message}}
}

func parseUUID(s string) (uuid.UUID, error) {
	return uuid.Parse(s)
}
