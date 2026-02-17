package users

import (
	"crypto/sha256"
	"fmt"

	"github.com/google/uuid"
	"golang.org/x/crypto/bcrypt"
)

// EventPublisher publishes domain events to registered listeners.
type EventPublisher interface {
	Publish(event any)
}

// Service encapsulates User domain logic.
// No framework imports — pure business logic only (DDD domain layer contract).
type Service struct {
	repo      Repository
	publisher EventPublisher
}

// NewService constructs a UserService with its required dependencies.
func NewService(repo Repository, publisher EventPublisher) *Service {
	return &Service{repo: repo, publisher: publisher}
}

// CreateUser hashes the password (bcrypt cost 12 — A.10) and persists the user.
// Emits UserCreatedEvent for the audit listener (A.12).
func (s *Service) CreateUser(email, password, role string) (*User, error) {
	exists, err := s.repo.ExistsByEmail(email)
	if err != nil {
		return nil, err
	}
	if exists {
		return nil, ErrConflict{Message: "email already exists"}
	}

	hashed, err := bcrypt.GenerateFromPassword([]byte(password), 12) // A.10: cost ≥ 12
	if err != nil {
		return nil, err
	}

	u := &User{
		Email:          email,
		HashedPassword: string(hashed),
		Role:           role,
		IsActive:       true,
	}
	saved, err := s.repo.Save(u)
	if err != nil {
		return nil, err
	}

	// A.12: emit domain event with email_hash only — never raw PII
	emailHash := fmt.Sprintf("%x", sha256.Sum256([]byte(email)))
	s.publisher.Publish(UserCreatedEvent{
		UserID:    saved.ID.String(),
		EmailHash: emailHash,
		Role:      saved.Role,
	})

	return saved, nil
}

// ListUsers returns a paginated slice of active users.
func (s *Service) ListUsers(skip, limit int) ([]*User, error) {
	return s.repo.FindAll(skip, limit)
}

// UpdateUser applies partial updates; rejects duplicate email (A.9).
func (s *Service) UpdateUser(u *User, email, fullName string) (*User, error) {
	if email != "" && email != u.Email {
		exists, err := s.repo.ExistsByEmail(email)
		if err != nil {
			return nil, err
		}
		if exists {
			return nil, ErrConflict{Message: "email already exists"}
		}
		u.Email = email
	}
	if fullName != "" {
		u.FullName = fullName
	}
	return s.repo.Save(u)
}

// DeleteUser soft-deletes a user, preserving the audit trail (A.12).
func (s *Service) DeleteUser(id string) error {
	uid, err := uuid.Parse(id)
	if err != nil {
		return err
	}
	return s.repo.SoftDelete(uid)
}

// VerifyPassword performs constant-time bcrypt comparison (A.10).
func (s *Service) VerifyPassword(plain, hashed string) bool {
	return bcrypt.CompareHashAndPassword([]byte(hashed), []byte(plain)) == nil
}

// ErrConflict signals a domain uniqueness violation.
type ErrConflict struct{ Message string }

func (e ErrConflict) Error() string { return e.Message }
