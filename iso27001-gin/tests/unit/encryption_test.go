package unit_test

import (
	"testing"

	"github.com/iso27001/gin-blueprint/internal/infrastructure/encryption"
)

func TestFieldEncryptor_RoundTrip(t *testing.T) {
	enc, err := encryption.New("test-only-key-exactly-32bytes-ab")
	if err != nil {
		t.Fatalf("New() failed: %v", err)
	}

	plaintext := "user@example.com"
	ciphertext, err := enc.Encrypt(plaintext)
	if err != nil {
		t.Fatalf("Encrypt() failed: %v", err)
	}

	if ciphertext == plaintext {
		t.Error("ciphertext should differ from plaintext")
	}

	decrypted, err := enc.Decrypt(ciphertext)
	if err != nil {
		t.Fatalf("Decrypt() failed: %v", err)
	}

	if decrypted != plaintext {
		t.Errorf("expected %q, got %q", plaintext, decrypted)
	}
}

func TestFieldEncryptor_DifferentIVEachEncryption(t *testing.T) {
	enc, _ := encryption.New("test-only-key-exactly-32bytes-ab")
	plaintext := "same-value"

	ct1, _ := enc.Encrypt(plaintext)
	ct2, _ := enc.Encrypt(plaintext)

	if ct1 == ct2 {
		t.Error("each encryption should produce a different ciphertext (random IV)")
	}
}

func TestFieldEncryptor_InvalidKeyLength(t *testing.T) {
	_, err := encryption.New("short-key")
	if err == nil {
		t.Error("expected error for key != 32 bytes")
	}
}
