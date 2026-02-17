// Package auth provides JWT token creation and validation (A.9).
package auth

import (
	"errors"
	"time"

	"github.com/golang-jwt/jwt/v5"
	"github.com/google/uuid"
)

// Claims embeds standard JWT claims and adds role + jti for RBAC and revocation.
type Claims struct {
	Role string `json:"role"`
	jwt.RegisteredClaims
}

// TokenPair is the response body for login and refresh endpoints.
type TokenPair struct {
	AccessToken  string `json:"access_token"`
	RefreshToken string `json:"refresh_token"`
	TokenType    string `json:"token_type"`
}

// IssueTokenPair creates a short-lived access token (30 min) and a long-lived
// refresh token (7 days). Both embed the user's role for RBAC (A.9).
func IssueTokenPair(userID, role, secret string, accessMinutes, refreshDays int) (TokenPair, error) {
	now := time.Now().UTC()

	access, err := sign(secret, Claims{
		Role: role,
		RegisteredClaims: jwt.RegisteredClaims{
			Subject:   userID,
			IssuedAt:  jwt.NewNumericDate(now),
			ExpiresAt: jwt.NewNumericDate(now.Add(time.Duration(accessMinutes) * time.Minute)),
			ID:        uuid.NewString(), // jti — A.9: replay protection
		},
	})
	if err != nil {
		return TokenPair{}, err
	}

	refresh, err := sign(secret, Claims{
		Role: role,
		RegisteredClaims: jwt.RegisteredClaims{
			Subject:   userID,
			IssuedAt:  jwt.NewNumericDate(now),
			ExpiresAt: jwt.NewNumericDate(now.AddDate(0, 0, refreshDays)),
			ID:        uuid.NewString(),
		},
	})
	if err != nil {
		return TokenPair{}, err
	}

	return TokenPair{
		AccessToken:  access,
		RefreshToken: refresh,
		TokenType:    "bearer",
	}, nil
}

// Verify parses and validates a token string, returning its claims.
func Verify(tokenStr, secret string) (*Claims, error) {
	token, err := jwt.ParseWithClaims(tokenStr, &Claims{}, func(t *jwt.Token) (any, error) {
		if _, ok := t.Method.(*jwt.SigningMethodHMAC); !ok {
			return nil, errors.New("unexpected signing method")
		}
		return []byte(secret), nil
	})
	if err != nil {
		return nil, err
	}
	claims, ok := token.Claims.(*Claims)
	if !ok || !token.Valid {
		return nil, errors.New("invalid token claims")
	}
	return claims, nil
}

func sign(secret string, claims Claims) (string, error) {
	return jwt.NewWithClaims(jwt.SigningMethodHS256, claims).SignedString([]byte(secret))
}
