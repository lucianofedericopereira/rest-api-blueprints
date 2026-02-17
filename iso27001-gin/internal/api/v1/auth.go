// Package v1 contains all HTTP handlers for API version 1.
package v1

import (
	"net/http"

	"github.com/gin-gonic/gin"
	"github.com/iso27001/gin-blueprint/internal/core/auth"
	"github.com/iso27001/gin-blueprint/internal/core/middleware"
	"github.com/iso27001/gin-blueprint/internal/domain/users"
	"github.com/iso27001/gin-blueprint/internal/core/config"
)

type loginRequest struct {
	Email    string `json:"email"    binding:"required,email"`
	Password string `json:"password" binding:"required"`
}

type refreshRequest struct {
	RefreshToken string `json:"refresh_token" binding:"required"`
}

// AuthHandler handles authentication endpoints (A.9).
type AuthHandler struct {
	repo    users.Repository
	svc     *users.Service
	bf      *middleware.BruteForceGuard
	cfg     *config.Config
}

// NewAuthHandler constructs the auth handler.
func NewAuthHandler(repo users.Repository, svc *users.Service, bf *middleware.BruteForceGuard, cfg *config.Config) *AuthHandler {
	return &AuthHandler{repo: repo, svc: svc, bf: bf, cfg: cfg}
}

// Login authenticates and issues a JWT pair.
// POST /api/v1/auth/login
func (h *AuthHandler) Login(c *gin.Context) {
	var req loginRequest
	if err := c.ShouldBindJSON(&req); err != nil {
		c.JSON(http.StatusBadRequest, errorResp("VALIDATION_ERROR", err.Error()))
		return
	}

	// A.9: brute-force check
	if !h.bf.Check(c, req.Email) {
		return // already aborted by BruteForceGuard.Check
	}

	user, err := h.repo.FindByEmail(req.Email)
	if err != nil || user == nil || !h.svc.VerifyPassword(req.Password, user.HashedPassword) {
		h.bf.RecordFailure(req.Email)
		c.JSON(http.StatusUnauthorized, errorResp("UNAUTHORIZED", "Invalid credentials"))
		return
	}

	if !user.IsActive {
		c.JSON(http.StatusUnauthorized, errorResp("UNAUTHORIZED", "User inactive"))
		return
	}

	h.bf.Clear(req.Email)

	pair, err := auth.IssueTokenPair(user.ID.String(), user.Role,
		h.cfg.JWTSecret, h.cfg.JWTAccessTokenExpireMin, h.cfg.JWTRefreshTokenExpireDays)
	if err != nil {
		c.JSON(http.StatusInternalServerError, errorResp("INTERNAL_ERROR", "Failed to issue tokens"))
		return
	}
	c.JSON(http.StatusOK, pair)
}

// Refresh exchanges a valid refresh token for a new token pair.
// POST /api/v1/auth/refresh
func (h *AuthHandler) Refresh(c *gin.Context) {
	var req refreshRequest
	if err := c.ShouldBindJSON(&req); err != nil {
		c.JSON(http.StatusBadRequest, errorResp("VALIDATION_ERROR", err.Error()))
		return
	}

	claims, err := auth.Verify(req.RefreshToken, h.cfg.JWTSecret)
	if err != nil {
		c.JSON(http.StatusUnauthorized, errorResp("UNAUTHORIZED", "Invalid or expired refresh token"))
		return
	}

	uid, parseErr := parseUUID(claims.Subject)
	if parseErr != nil {
		c.JSON(http.StatusUnauthorized, errorResp("UNAUTHORIZED", "Invalid token subject"))
		return
	}

	user, err := h.repo.FindByID(uid)
	if err != nil || user == nil || !user.IsActive {
		c.JSON(http.StatusUnauthorized, errorResp("UNAUTHORIZED", "User not found or inactive"))
		return
	}

	pair, err := auth.IssueTokenPair(user.ID.String(), user.Role,
		h.cfg.JWTSecret, h.cfg.JWTAccessTokenExpireMin, h.cfg.JWTRefreshTokenExpireDays)
	if err != nil {
		c.JSON(http.StatusInternalServerError, errorResp("INTERNAL_ERROR", "Failed to issue tokens"))
		return
	}
	c.JSON(http.StatusOK, pair)
}

// Logout acknowledges client-side token discard.
// POST /api/v1/auth/logout
func (h *AuthHandler) Logout(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{"message": "Logged out. Please discard your tokens."})
}
