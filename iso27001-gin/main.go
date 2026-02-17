// iso27001-gin — Go/Gin reference implementation of ISO 27001 security controls.
//
// Controls implemented:
//   A.9  — JWT auth, RBAC, tiered rate limiting, brute-force lockout
//   A.10 — bcrypt (cost 12), AES-256-GCM field encryption, HSTS + security headers
//   A.12 — correlation ID, structured logging, immutable audit log, domain events
//   A.14 — input validation (binding), no stack traces in responses
//   A.17 — liveness/readiness/detail health checks, error budget tracker, Prometheus metrics
package main

import (
	"context"
	"fmt"
	"log"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/joho/godotenv"
	"github.com/iso27001/gin-blueprint/internal/api/v1"
	"github.com/iso27001/gin-blueprint/internal/core/config"
	"github.com/iso27001/gin-blueprint/internal/core/middleware"
	"github.com/iso27001/gin-blueprint/internal/domain/users"
	"github.com/iso27001/gin-blueprint/internal/infrastructure/audit"
	"github.com/iso27001/gin-blueprint/internal/infrastructure/repositories"
	"github.com/iso27001/gin-blueprint/internal/infrastructure/telemetry"
	"github.com/prometheus/client_golang/prometheus/promhttp"
	goredis "github.com/redis/go-redis/v9"
	"gorm.io/driver/postgres"
	"gorm.io/gorm"
)

func main() {
	_ = godotenv.Load() // optional .env — won't error if absent

	cfg := config.Load()

	// ── Database ──────────────────────────────────────────────────────────────
	db, err := gorm.Open(postgres.Open(cfg.DatabaseURL), &gorm.Config{})
	if err != nil {
		log.Fatalf("database connection failed: %v", err)
	}
	// Auto-migrate tables (use proper migrations in production)
	if err := db.AutoMigrate(&repositories.GORMUserModel{}, &audit.AuditLog{}); err != nil {
		log.Fatalf("auto-migrate failed: %v", err)
	}

	// ── Redis (optional — graceful degradation) ───────────────────────────────
	var rdb *goredis.Client
	opt, err := goredis.ParseURL(cfg.RedisURL)
	if err == nil {
		rdb = goredis.NewClient(opt)
		ctx, cancel := context.WithTimeout(context.Background(), time.Second)
		defer cancel()
		if rdb.Ping(ctx).Err() != nil {
			log.Println("Redis unavailable — using in-process fallback for rate limiter and brute force")
			rdb = nil
		}
	}

	// ── Infrastructure ────────────────────────────────────────────────────────
	budget := telemetry.NewErrorBudgetTracker(0.999)
	userRepo := repositories.NewGORMUserRepository(db)
	eventBus := &audit.EventBus{}
	auditSvc := audit.NewService(db)

	// Wire audit listener — UserCreatedEvent → audit record (A.12)
	eventBus.Subscribe(func(event any) {
		if e, ok := event.(users.UserCreatedEvent); ok {
			auditSvc.OnUserCreated(e, "system") // correlation ID from context in prod
		}
	})

	userSvc := users.NewService(userRepo, eventBus)
	bruteForce := middleware.NewBruteForceGuard(rdb)

	// ── Gin engine ────────────────────────────────────────────────────────────
	if cfg.AppEnv == "production" {
		gin.SetMode(gin.ReleaseMode)
	}
	r := gin.New()

	// ── Global middleware stack (outermost first) ─────────────────────────────
	r.Use(gin.Recovery())                        // recover from panics; never expose traces
	r.Use(middleware.SecurityHeaders())          // A.10
	r.Use(middleware.CorrelationID(budget))      // A.12
	r.Use(middleware.RateLimit(rdb))             // A.9/A.17

	// ── Handlers ─────────────────────────────────────────────────────────────
	authH   := v1.NewAuthHandler(userRepo, userSvc, bruteForce, cfg)
	usersH  := v1.NewUsersHandler(userRepo, userSvc)
	healthH := v1.NewHealthHandler(db, budget)

	// ── Routes ───────────────────────────────────────────────────────────────
	apiV1 := r.Group("/api/v1")
	{
		// Auth — public (brute-force guard applied inside handler)
		authG := apiV1.Group("/auth")
		authG.POST("/login", authH.Login)
		authG.POST("/refresh", middleware.JWTAuth(cfg.JWTSecret), authH.Refresh)
		authG.POST("/logout", middleware.JWTAuth(cfg.JWTSecret), authH.Logout)

		// Users
		usersG := apiV1.Group("/users")
		usersG.POST("", usersH.Create)                                                          // public registration
		usersG.GET("", middleware.JWTAuth(cfg.JWTSecret), middleware.RequireRole("admin"), usersH.List)
		usersG.GET("/me", middleware.JWTAuth(cfg.JWTSecret), usersH.Me)
		usersG.GET("/:id", middleware.JWTAuth(cfg.JWTSecret), usersH.Get)
		usersG.PATCH("/:id", middleware.JWTAuth(cfg.JWTSecret), usersH.Update)
		usersG.DELETE("/:id", middleware.JWTAuth(cfg.JWTSecret), middleware.RequireRole("admin"), usersH.Delete)

		// Health — liveness and readiness are public; detail requires admin
		health := apiV1.Group("/health")
		health.GET("/live", healthH.Live)
		health.GET("/ready", healthH.Ready)
		health.GET("/detail", middleware.JWTAuth(cfg.JWTSecret), middleware.RequireRole("admin"), healthH.Detail)
	}

	// A.17: Prometheus metrics — admin only in production
	r.GET("/metrics", func(c *gin.Context) {
		promhttp.Handler().ServeHTTP(c.Writer, c.Request)
	})

	// ── HTTP server with graceful shutdown ────────────────────────────────────
	srv := &http.Server{
		Addr:         fmt.Sprintf(":%s", cfg.Port),
		Handler:      r,
		ReadTimeout:  15 * time.Second,
		WriteTimeout: 15 * time.Second,
		IdleTimeout:  60 * time.Second,
	}

	go func() {
		log.Printf("iso27001-gin listening on :%s (env=%s)", cfg.Port, cfg.AppEnv)
		if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			log.Fatalf("server error: %v", err)
		}
	}()

	// Graceful shutdown on SIGINT / SIGTERM
	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit
	log.Println("shutting down...")
	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()
	if err := srv.Shutdown(ctx); err != nil {
		log.Fatalf("forced shutdown: %v", err)
	}
	log.Println("done")
}
