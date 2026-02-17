// Package repositories provides GORM-backed implementations of domain repository interfaces.
package repositories

import (
	"time"

	"github.com/google/uuid"
	"github.com/iso27001/gin-blueprint/internal/domain/users"
	"gorm.io/gorm"
)

// GORMUserModel is the GORM model for the users table (exported for AutoMigrate).
// Kept in infrastructure so ORM tags stay out of the domain layer.
type GORMUserModel = userModel

type userModel struct {
	ID             uuid.UUID  `gorm:"type:uuid;primaryKey"`
	Email          string     `gorm:"uniqueIndex;not null"`
	HashedPassword string     `gorm:"not null"`
	FullName       string
	Role           string    `gorm:"default:viewer"`
	IsActive       bool      `gorm:"default:true"`
	CreatedAt      time.Time
	UpdatedAt      time.Time
	DeletedAt      *time.Time `gorm:"index"`
}

func (userModel) TableName() string { return "users" }

// GORMUserRepository implements users.Repository against PostgreSQL via GORM.
type GORMUserRepository struct {
	db *gorm.DB
}

// NewGORMUserRepository constructs the repository.
func NewGORMUserRepository(db *gorm.DB) *GORMUserRepository {
	return &GORMUserRepository{db: db}
}

func (r *GORMUserRepository) Save(u *users.User) (*users.User, error) {
	if u.ID == uuid.Nil {
		u.ID = uuid.New()
	}
	m := toModel(u)
	if err := r.db.Save(&m).Error; err != nil {
		return nil, err
	}
	return toDomain(&m), nil
}

func (r *GORMUserRepository) FindByEmail(email string) (*users.User, error) {
	var m userModel
	if err := r.db.Where("email = ? AND deleted_at IS NULL", email).First(&m).Error; err != nil {
		if err == gorm.ErrRecordNotFound {
			return nil, nil
		}
		return nil, err
	}
	return toDomain(&m), nil
}

func (r *GORMUserRepository) FindByID(id uuid.UUID) (*users.User, error) {
	var m userModel
	if err := r.db.Where("id = ? AND deleted_at IS NULL", id).First(&m).Error; err != nil {
		if err == gorm.ErrRecordNotFound {
			return nil, nil
		}
		return nil, err
	}
	return toDomain(&m), nil
}

func (r *GORMUserRepository) FindAll(skip, limit int) ([]*users.User, error) {
	var ms []userModel
	if err := r.db.Where("deleted_at IS NULL").Offset(skip).Limit(limit).Find(&ms).Error; err != nil {
		return nil, err
	}
	result := make([]*users.User, len(ms))
	for i, m := range ms {
		result[i] = toDomain(&m)
	}
	return result, nil
}

// SoftDelete sets deleted_at — preserves audit trail (A.12).
func (r *GORMUserRepository) SoftDelete(id uuid.UUID) error {
	now := time.Now()
	return r.db.Model(&userModel{}).Where("id = ?", id).Update("deleted_at", &now).Error
}

func (r *GORMUserRepository) ExistsByEmail(email string) (bool, error) {
	var count int64
	if err := r.db.Model(&userModel{}).Where("email = ? AND deleted_at IS NULL", email).Count(&count).Error; err != nil {
		return false, err
	}
	return count > 0, nil
}

// ── mapping helpers ───────────────────────────────────────────────────────────

func toModel(u *users.User) userModel {
	return userModel{
		ID:             u.ID,
		Email:          u.Email,
		HashedPassword: u.HashedPassword,
		FullName:       u.FullName,
		Role:           u.Role,
		IsActive:       u.IsActive,
	}
}

func toDomain(m *userModel) *users.User {
	return &users.User{
		ID:             m.ID,
		Email:          m.Email,
		HashedPassword: m.HashedPassword,
		FullName:       m.FullName,
		Role:           m.Role,
		IsActive:       m.IsActive,
		CreatedAt:      m.CreatedAt,
		UpdatedAt:      m.UpdatedAt,
		DeletedAt:      m.DeletedAt,
	}
}
