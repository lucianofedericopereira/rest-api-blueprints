package unit_test

import (
	"testing"

	"github.com/iso27001/gin-blueprint/internal/core/auth"
)

const testSecret = "test-secret-key-exactly-32-bytes!!"

func TestIssueAndVerifyTokenPair(t *testing.T) {
	pair, err := auth.IssueTokenPair("user-123", "admin", testSecret, 30, 7)
	if err != nil {
		t.Fatalf("IssueTokenPair() failed: %v", err)
	}
	if pair.AccessToken == "" || pair.RefreshToken == "" {
		t.Error("expected non-empty tokens")
	}
	if pair.TokenType != "bearer" {
		t.Errorf("expected token_type=bearer, got %s", pair.TokenType)
	}

	claims, err := auth.Verify(pair.AccessToken, testSecret)
	if err != nil {
		t.Fatalf("Verify() failed: %v", err)
	}
	if claims.Subject != "user-123" {
		t.Errorf("expected subject=user-123, got %s", claims.Subject)
	}
	if claims.Role != "admin" {
		t.Errorf("expected role=admin, got %s", claims.Role)
	}
}

func TestVerify_InvalidToken(t *testing.T) {
	_, err := auth.Verify("not-a-valid-token", testSecret)
	if err == nil {
		t.Error("expected error for invalid token")
	}
}

func TestVerify_WrongSecret(t *testing.T) {
	pair, _ := auth.IssueTokenPair("u1", "viewer", testSecret, 30, 7)
	_, err := auth.Verify(pair.AccessToken, "wrong-secret")
	if err == nil {
		t.Error("expected error when verifying with wrong secret")
	}
}

func TestIssueTokenPair_UniqueJTIs(t *testing.T) {
	p1, _ := auth.IssueTokenPair("u1", "viewer", testSecret, 30, 7)
	p2, _ := auth.IssueTokenPair("u1", "viewer", testSecret, 30, 7)
	if p1.AccessToken == p2.AccessToken {
		t.Error("each token pair should have unique JTIs")
	}
}
