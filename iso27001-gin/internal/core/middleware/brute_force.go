package middleware

// A.9: Brute-force login protection.
//
// Tracks failed authentication attempts per account identifier (email).
// Uses Redis when available (cross-process); falls back to in-process map.
//
// Policy:
//   - MaxAttempts : 5 consecutive failures trigger a lockout
//   - LockoutTTL  : 15 minutes (900 seconds)
//   - Cleared     : on successful login via Clear()

import (
	"context"
	"fmt"
	"net/http"
	"strconv"
	"sync"
	"time"

	"github.com/gin-gonic/gin"
	goredis "github.com/redis/go-redis/v9"
)

const (
	bruteMaxAttempts = 5
	bruteLockoutTTL  = 15 * time.Minute
	bruteKeyPrefix   = "brute_force:"
)

type localEntry struct {
	count       int
	lockedUntil time.Time
}

// BruteForceGuard checks and records failed auth attempts.
type BruteForceGuard struct {
	rdb *goredis.Client
	mu  sync.Mutex
	mem map[string]*localEntry
}

// NewBruteForceGuard creates a guard; pass nil rdb to use in-process only.
func NewBruteForceGuard(rdb *goredis.Client) *BruteForceGuard {
	return &BruteForceGuard{rdb: rdb, mem: map[string]*localEntry{}}
}

// Check aborts with 429 if the identifier is currently locked out.
func (g *BruteForceGuard) Check(c *gin.Context, identifier string) bool {
	if g.isLocked(identifier) {
		c.AbortWithStatusJSON(http.StatusTooManyRequests, gin.H{
			"error": gin.H{
				"code":    "ACCOUNT_LOCKED",
				"message": "Too many failed attempts. Account temporarily locked.",
			},
		})
		return false
	}
	return true
}

// RecordFailure increments the failure counter, locking on threshold breach.
func (g *BruteForceGuard) RecordFailure(identifier string) {
	if g.rdb != nil {
		ctx, cancel := context.WithTimeout(context.Background(), 300*time.Millisecond)
		defer cancel()
		keyCount := fmt.Sprintf("%s%s:count", bruteKeyPrefix, identifier)
		keyLock := fmt.Sprintf("%s%s:locked_until", bruteKeyPrefix, identifier)
		count, _ := g.rdb.Incr(ctx, keyCount).Result()
		g.rdb.Expire(ctx, keyCount, bruteLockoutTTL)
		if count >= bruteMaxAttempts {
			g.rdb.Set(ctx, keyLock, time.Now().Add(bruteLockoutTTL).Unix(), bruteLockoutTTL)
			g.rdb.Del(ctx, keyCount)
		}
		return
	}
	g.mu.Lock()
	defer g.mu.Unlock()
	e := g.memEntry(identifier)
	e.count++
	if e.count >= bruteMaxAttempts {
		e.lockedUntil = time.Now().Add(bruteLockoutTTL)
		e.count = 0
	}
}

// Clear resets the failure state after a successful login.
func (g *BruteForceGuard) Clear(identifier string) {
	if g.rdb != nil {
		ctx, cancel := context.WithTimeout(context.Background(), 300*time.Millisecond)
		defer cancel()
		g.rdb.Del(ctx,
			fmt.Sprintf("%s%s:count", bruteKeyPrefix, identifier),
			fmt.Sprintf("%s%s:locked_until", bruteKeyPrefix, identifier),
		)
		return
	}
	g.mu.Lock()
	defer g.mu.Unlock()
	delete(g.mem, identifier)
}

func (g *BruteForceGuard) isLocked(identifier string) bool {
	if g.rdb != nil {
		ctx, cancel := context.WithTimeout(context.Background(), 300*time.Millisecond)
		defer cancel()
		val, err := g.rdb.Get(ctx, fmt.Sprintf("%s%s:locked_until", bruteKeyPrefix, identifier)).Result()
		if err != nil {
			return false
		}
		ts, err := strconv.ParseInt(val, 10, 64)
		if err != nil {
			return false
		}
		return time.Now().Unix() < ts
	}
	g.mu.Lock()
	defer g.mu.Unlock()
	e := g.mem[identifier]
	return e != nil && time.Now().Before(e.lockedUntil)
}

func (g *BruteForceGuard) memEntry(identifier string) *localEntry {
	if g.mem[identifier] == nil {
		g.mem[identifier] = &localEntry{}
	}
	return g.mem[identifier]
}
