package unit_test

import (
	"net/http"
	"net/http/httptest"
	"testing"

	"github.com/gin-gonic/gin"
	"github.com/iso27001/gin-blueprint/internal/core/middleware"
)

func init() { gin.SetMode(gin.TestMode) }

func newTestContext() (*gin.Context, *httptest.ResponseRecorder) {
	w := httptest.NewRecorder()
	c, _ := gin.CreateTestContext(w)
	c.Request = httptest.NewRequest(http.MethodPost, "/", nil)
	return c, w
}

func TestBruteForce_AllowsBeforeThreshold(t *testing.T) {
	guard := middleware.NewBruteForceGuard(nil)
	c, _ := newTestContext()

	for i := 0; i < 4; i++ {
		guard.RecordFailure("test@example.com")
	}
	ok := guard.Check(c, "test@example.com")
	if !ok {
		t.Error("should allow before reaching 5 failures")
	}
}

func TestBruteForce_LocksAfterFiveFailures(t *testing.T) {
	guard := middleware.NewBruteForceGuard(nil)

	for i := 0; i < 5; i++ {
		guard.RecordFailure("locked@example.com")
	}

	c, w := newTestContext()
	ok := guard.Check(c, "locked@example.com")
	if ok {
		t.Error("should be locked after 5 failures")
	}
	if w.Code != http.StatusTooManyRequests {
		t.Errorf("expected 429, got %d", w.Code)
	}
}

func TestBruteForce_ClearResetsLock(t *testing.T) {
	guard := middleware.NewBruteForceGuard(nil)

	for i := 0; i < 5; i++ {
		guard.RecordFailure("reset@example.com")
	}
	guard.Clear("reset@example.com")

	c, _ := newTestContext()
	ok := guard.Check(c, "reset@example.com")
	if !ok {
		t.Error("should be unlocked after Clear()")
	}
}
