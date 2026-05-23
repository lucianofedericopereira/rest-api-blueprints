// Package auth provides JWT token creation and validation (A.9).
package auth

import (
	"errors"
	"time"

	"github.com/golang-jwt/jwt/v5"
	"github.com/google/uuid"
)

// Token-type constants distinguish access tokens from refresh tokens (A.9.4).
// A bearer endpoint must require AccessTokenTyp; /auth/refresh must require
// RefreshTokenTyp. Without this discrimination, a stolen access token could be
// presented at /auth/refresh and exchanged for a fresh long-lived token pair,
// defeating the short access-token lifetime.
const (
	AccessTokenTyp  = "access"
	RefreshTokenTyp = "refresh"
)

// ErrUnexpectedTokenTyp is returned when a token's typ claim does not match
// the value the caller required.
var ErrUnexpectedTokenTyp = errors.New("unexpected token typ")

// Claims embeds standard JWT claims and adds role + typ for RBAC and
// access/refresh discrimination. The jti lives on RegisteredClaims.ID.
type Claims struct {
	Role string `json:"role"`
	Typ  string `json:"typ"`
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
		Typ:  AccessTokenTyp,
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
		Typ:  RefreshTokenTyp,
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
//
// It does NOT enforce the typ claim — callers that need access/refresh
// discrimination should use VerifyTyped (or the AccessTokenTyp /
// RefreshTokenTyp convenience constants) so the rule is uniform across
// handlers and middleware.
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

// VerifyTyped is Verify plus a typ-claim check. Pass AccessTokenTyp at bearer
// endpoints and RefreshTokenTyp at /auth/refresh. A mismatch returns
// ErrUnexpectedTokenTyp so it can be distinguished from signature/expiry
// errors in logs (A.9.4).
func VerifyTyped(tokenStr, secret, expectedTyp string) (*Claims, error) {
	claims, err := Verify(tokenStr, secret)
	if err != nil {
		return nil, err
	}
	if claims.Typ != expectedTyp {
		return nil, ErrUnexpectedTokenTyp
	}
	return claims, nil
}

func sign(secret string, claims Claims) (string, error) {
	return jwt.NewWithClaims(jwt.SigningMethodHS256, claims).SignedString([]byte(secret))
}
