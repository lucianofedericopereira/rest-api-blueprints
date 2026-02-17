// Package config loads environment-based application settings.
package config

import (
	"os"
	"strconv"
)

// Config holds all application configuration. Values are loaded from
// environment variables at startup; zero values mean "use default".
type Config struct {
	AppName    string
	AppVersion string
	AppEnv     string

	// A.10: JWT secrets — must be overridden in production via secrets manager.
	JWTSecret                 string
	JWTAccessTokenExpireMin   int
	JWTRefreshTokenExpireDays int

	// A.10: AES-256-GCM field encryption key (must be exactly 32 bytes).
	EncryptionKey string

	// A.9: CORS allowed origins (comma-separated, no wildcard in production).
	CORSAllowedOrigins string

	DatabaseURL string
	RedisURL    string

	// A.9: Server port
	Port string
}

func Load() *Config {
	return &Config{
		AppName:                   env("APP_NAME", "iso27001-gin"),
		AppVersion:                env("APP_VERSION", "1.0.0"),
		AppEnv:                    env("APP_ENV", "development"),
		JWTSecret:                 env("JWT_SECRET", "change-me-in-production"),
		JWTAccessTokenExpireMin:   envInt("JWT_ACCESS_TOKEN_EXPIRE_MINUTES", 30),
		JWTRefreshTokenExpireDays: envInt("JWT_REFRESH_TOKEN_EXPIRE_DAYS", 7),
		EncryptionKey:             env("ENCRYPTION_KEY", "dev-only-32-byte-key-change-me!!"),
		CORSAllowedOrigins:        env("CORS_ALLOWED_ORIGINS", "http://localhost:3000,http://localhost:8080"),
		DatabaseURL:               env("DATABASE_URL", "host=localhost user=user password=pass dbname=iso27001 sslmode=disable"),
		RedisURL:                  env("REDIS_URL", "redis://localhost:6379/0"),
		Port:                      env("PORT", "8003"),
	}
}

func env(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return fallback
}

func envInt(key string, fallback int) int {
	if v := os.Getenv(key); v != "" {
		if n, err := strconv.Atoi(v); err == nil {
			return n
		}
	}
	return fallback
}
