// Package encryption provides AES-256-GCM field-level encryption for PII at rest (A.10).
package encryption

import (
	"crypto/aes"
	"crypto/cipher"
	"crypto/rand"
	"encoding/base64"
	"errors"
	"io"
)

const (
	ivLength  = 12 // AES-GCM standard nonce
	tagLength = 16 // GCM auth tag
)

// FieldEncryptor encrypts and decrypts string fields using AES-256-GCM.
// Wire format (base64): IV (12 bytes) || TAG (16 bytes) || ciphertext.
// Matches the FastAPI, Symfony, Laravel, NestJS, and Spring Boot implementations.
type FieldEncryptor struct {
	aead cipher.AEAD
}

// New constructs a FieldEncryptor from a 32-byte UTF-8 key (ENCRYPTION_KEY env var).
func New(key string) (*FieldEncryptor, error) {
	k := []byte(key)
	if len(k) != 32 {
		return nil, errors.New("ENCRYPTION_KEY must be exactly 32 bytes when UTF-8 encoded")
	}
	block, err := aes.NewCipher(k)
	if err != nil {
		return nil, err
	}
	aead, err := cipher.NewGCM(block)
	if err != nil {
		return nil, err
	}
	return &FieldEncryptor{aead: aead}, nil
}

// Encrypt encrypts plaintext and returns a base64-encoded string.
func (f *FieldEncryptor) Encrypt(plaintext string) (string, error) {
	iv := make([]byte, ivLength)
	if _, err := io.ReadFull(rand.Reader, iv); err != nil {
		return "", err
	}
	// GCM.Seal appends the auth tag to the ciphertext automatically
	ciphertextWithTag := f.aead.Seal(nil, iv, []byte(plaintext), nil)
	raw := append(iv, ciphertextWithTag...) //nolint:gocritic
	return base64.StdEncoding.EncodeToString(raw), nil
}

// Decrypt decodes a base64 ciphertext and returns the plaintext.
func (f *FieldEncryptor) Decrypt(ciphertext string) (string, error) {
	raw, err := base64.StdEncoding.DecodeString(ciphertext)
	if err != nil {
		return "", err
	}
	if len(raw) < ivLength+tagLength {
		return "", errors.New("ciphertext too short")
	}
	iv := raw[:ivLength]
	ciphertextWithTag := raw[ivLength:]
	plaintext, err := f.aead.Open(nil, iv, ciphertextWithTag, nil)
	if err != nil {
		return "", err
	}
	return string(plaintext), nil
}
