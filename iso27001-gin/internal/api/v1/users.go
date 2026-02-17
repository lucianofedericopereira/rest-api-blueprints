package v1

import (
	"net/http"
	"strconv"

	"github.com/gin-gonic/gin"
	"github.com/iso27001/gin-blueprint/internal/domain/users"
)

type createUserRequest struct {
	Email    string `json:"email"    binding:"required,email"`
	Password string `json:"password" binding:"required,min=12"`
	Role     string `json:"role"     binding:"omitempty,oneof=admin manager analyst viewer"`
}

type updateUserRequest struct {
	Email    string `json:"email"     binding:"omitempty,email"`
	FullName string `json:"full_name"`
}

type userResponse struct {
	ID        string `json:"id"`
	Email     string `json:"email"`
	FullName  string `json:"full_name,omitempty"`
	Role      string `json:"role"`
	IsActive  bool   `json:"is_active"`
	CreatedAt string `json:"created_at"`
}

func toUserResponse(u *users.User) userResponse {
	return userResponse{
		ID:        u.ID.String(),
		Email:     u.Email,
		FullName:  u.FullName,
		Role:      u.Role,
		IsActive:  u.IsActive,
		CreatedAt: u.CreatedAt.Format("2006-01-02T15:04:05Z07:00"),
	}
}

// UsersHandler handles user CRUD endpoints (A.9).
type UsersHandler struct {
	repo users.Repository
	svc  *users.Service
}

// NewUsersHandler constructs the users handler.
func NewUsersHandler(repo users.Repository, svc *users.Service) *UsersHandler {
	return &UsersHandler{repo: repo, svc: svc}
}

// Create registers a new user.
// POST /api/v1/users
func (h *UsersHandler) Create(c *gin.Context) {
	var req createUserRequest
	if err := c.ShouldBindJSON(&req); err != nil {
		c.JSON(http.StatusBadRequest, errorResp("VALIDATION_ERROR", err.Error()))
		return
	}
	role := req.Role
	if role == "" {
		role = users.RoleViewer
	}
	user, err := h.svc.CreateUser(req.Email, req.Password, role)
	if err != nil {
		if _, ok := err.(users.ErrConflict); ok {
			c.JSON(http.StatusConflict, errorResp("CONFLICT", err.Error()))
			return
		}
		c.JSON(http.StatusInternalServerError, errorResp("INTERNAL_ERROR", "Failed to create user"))
		return
	}
	c.JSON(http.StatusCreated, toUserResponse(user))
}

// List returns a paginated list of users (admin only).
// GET /api/v1/users
func (h *UsersHandler) List(c *gin.Context) {
	skip, _ := strconv.Atoi(c.DefaultQuery("skip", "0"))
	limit, _ := strconv.Atoi(c.DefaultQuery("limit", "20"))
	if limit > 100 {
		limit = 100
	}
	list, err := h.svc.ListUsers(skip, limit)
	if err != nil {
		c.JSON(http.StatusInternalServerError, errorResp("INTERNAL_ERROR", "Failed to list users"))
		return
	}
	resp := make([]userResponse, len(list))
	for i, u := range list {
		resp[i] = toUserResponse(u)
	}
	c.JSON(http.StatusOK, resp)
}

// Me returns the current authenticated user's profile.
// GET /api/v1/users/me
func (h *UsersHandler) Me(c *gin.Context) {
	userID, _ := c.Get("user_id")
	uid, err := parseUUID(userID.(string))
	if err != nil {
		c.JSON(http.StatusUnauthorized, errorResp("UNAUTHORIZED", "Invalid token"))
		return
	}
	user, err := h.repo.FindByID(uid)
	if err != nil || user == nil {
		c.JSON(http.StatusNotFound, errorResp("NOT_FOUND", "User not found"))
		return
	}
	c.JSON(http.StatusOK, toUserResponse(user))
}

// Get returns a user by ID (owner or admin).
// GET /api/v1/users/:id
func (h *UsersHandler) Get(c *gin.Context) {
	uid, err := parseUUID(c.Param("id"))
	if err != nil {
		c.JSON(http.StatusBadRequest, errorResp("VALIDATION_ERROR", "Invalid user ID"))
		return
	}
	// A.9: enforce ownership check
	if !h.isOwnerOrAdmin(c, uid.String()) {
		c.JSON(http.StatusForbidden, errorResp("FORBIDDEN", "Access denied"))
		return
	}
	user, err := h.repo.FindByID(uid)
	if err != nil || user == nil {
		c.JSON(http.StatusNotFound, errorResp("NOT_FOUND", "User not found"))
		return
	}
	c.JSON(http.StatusOK, toUserResponse(user))
}

// Update partially updates a user (owner or admin).
// PATCH /api/v1/users/:id
func (h *UsersHandler) Update(c *gin.Context) {
	uid, err := parseUUID(c.Param("id"))
	if err != nil {
		c.JSON(http.StatusBadRequest, errorResp("VALIDATION_ERROR", "Invalid user ID"))
		return
	}
	if !h.isOwnerOrAdmin(c, uid.String()) {
		c.JSON(http.StatusForbidden, errorResp("FORBIDDEN", "Access denied"))
		return
	}
	var req updateUserRequest
	if err := c.ShouldBindJSON(&req); err != nil {
		c.JSON(http.StatusBadRequest, errorResp("VALIDATION_ERROR", err.Error()))
		return
	}
	user, err := h.repo.FindByID(uid)
	if err != nil || user == nil {
		c.JSON(http.StatusNotFound, errorResp("NOT_FOUND", "User not found"))
		return
	}
	updated, err := h.svc.UpdateUser(user, req.Email, req.FullName)
	if err != nil {
		if _, ok := err.(users.ErrConflict); ok {
			c.JSON(http.StatusConflict, errorResp("CONFLICT", err.Error()))
			return
		}
		c.JSON(http.StatusInternalServerError, errorResp("INTERNAL_ERROR", "Update failed"))
		return
	}
	c.JSON(http.StatusOK, toUserResponse(updated))
}

// Delete soft-deletes a user (admin only).
// DELETE /api/v1/users/:id
func (h *UsersHandler) Delete(c *gin.Context) {
	uid, err := parseUUID(c.Param("id"))
	if err != nil {
		c.JSON(http.StatusBadRequest, errorResp("VALIDATION_ERROR", "Invalid user ID"))
		return
	}
	if err := h.svc.DeleteUser(uid.String()); err != nil {
		c.JSON(http.StatusNotFound, errorResp("NOT_FOUND", "User not found"))
		return
	}
	c.Status(http.StatusNoContent)
}

// isOwnerOrAdmin checks if the current user is the resource owner or has admin role.
func (h *UsersHandler) isOwnerOrAdmin(c *gin.Context, resourceUserID string) bool {
	currentID, _ := c.Get("user_id")
	role, _ := c.Get("user_role")
	return currentID.(string) == resourceUserID || role.(string) == users.RoleAdmin
}
