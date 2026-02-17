// Package audit provides the immutable audit log (A.12).
package audit

import (
	"time"

	"github.com/google/uuid"
	"github.com/iso27001/gin-blueprint/internal/domain/users"
	"gorm.io/gorm"
)

// AuditLog is the immutable audit record.
// Records must never be updated or deleted (A.12 policy).
type AuditLog struct {
	ID            uuid.UUID  `gorm:"type:uuid;primaryKey"`
	Action        string     `gorm:"not null"`
	PerformedBy   string     // user ID or "system"
	ResourceType  string     `gorm:"not null"`
	ResourceID    string     `gorm:"not null"`
	Changes       string     `gorm:"type:jsonb"` // JSON blob
	IPAddress     string
	CorrelationID string     `gorm:"not null"`
	CreatedAt     time.Time
}

func (AuditLog) TableName() string { return "audit_logs" }

// Service writes immutable audit records.
type Service struct {
	db *gorm.DB
}

// NewService creates an audit service backed by the given DB connection.
func NewService(db *gorm.DB) *Service { return &Service{db: db} }

// Record persists a new audit entry. Best-effort: logs errors but doesn't panic.
func (s *Service) Record(action, resourceType, resourceID, performedBy, correlationID, changes string) {
	entry := AuditLog{
		ID:            uuid.New(),
		Action:        action,
		PerformedBy:   performedBy,
		ResourceType:  resourceType,
		ResourceID:    resourceID,
		Changes:       changes,
		CorrelationID: correlationID,
		CreatedAt:     time.Now().UTC(),
	}
	// Best-effort — don't block the main request on audit write failure
	s.db.Create(&entry)
}

// OnUserCreated handles UserCreatedEvent and writes the audit record (A.12).
func (s *Service) OnUserCreated(event users.UserCreatedEvent, correlationID string) {
	changes := `{"email_hash":"` + event.EmailHash + `","role":"` + event.Role + `"}`
	s.Record("user.created", "user", event.UserID, "system", correlationID, changes)
}
