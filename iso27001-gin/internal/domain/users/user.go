// Package users contains the User domain entity, repository interface, and service.
package users

import (
	"time"

	"github.com/google/uuid"
)

// Role constants — define the RBAC hierarchy (A.9).
const (
	RoleAdmin   = "admin"
	RoleManager = "manager"
	RoleAnalyst = "analyst"
	RoleViewer  = "viewer"
)

// User is the aggregate root for the users bounded context.
// Domain layer: no framework dependencies, no ORM tags here.
type User struct {
	ID             uuid.UUID
	Email          string
	HashedPassword string
	FullName       string
	Role           string
	IsActive       bool
	CreatedAt      time.Time
	UpdatedAt      time.Time
	DeletedAt      *time.Time // A.12: soft-delete preserves audit trail
}

// Repository defines the persistence contract for Users (A.9, A.12).
// Infrastructure layer provides the concrete implementation.
type Repository interface {
	Save(u *User) (*User, error)
	FindByEmail(email string) (*User, error)
	FindByID(id uuid.UUID) (*User, error)
	FindAll(skip, limit int) ([]*User, error)
	SoftDelete(id uuid.UUID) error
	ExistsByEmail(email string) (bool, error)
}

// UserCreatedEvent is emitted after a user is created (A.12: audit trail).
// Contains email_hash only — never raw email in domain events.
type UserCreatedEvent struct {
	UserID    string
	EmailHash string // sha256(email)
	Role      string
}
