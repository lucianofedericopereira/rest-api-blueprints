package middleware

import (
	"context"
	"fmt"
	"net/http"
	"strings"
	"sync"
	"time"

	"github.com/gin-gonic/gin"
	goredis "github.com/redis/go-redis/v9"
)

// A.9 / A.17: Tiered sliding-window rate limiter.
//
//	auth   — 10  req/min per IP  (brute-force protection)
//	write  — 30  req/min per IP  (POST/PUT/PATCH/DELETE)
//	global — 100 req/min per IP  (everything else)
//
// Redis-backed with goroutine-safe in-process fallback.
var tierLimits = map[string]int{
	"auth":   10,
	"write":  30,
	"global": 100,
}

// luaScript atomically removes expired entries, counts current window, and
// conditionally adds the new entry — prevents TOCTOU race on Redis.
const luaScript = `
local key = KEYS[1]
local limit = tonumber(ARGV[1])
local now   = tonumber(ARGV[2])
local window = tonumber(ARGV[3])
local clearBefore = now - window
redis.call('ZREMRANGEBYSCORE', key, 0, clearBefore)
local count = redis.call('ZCARD', key)
if count < limit then
    redis.call('ZADD', key, now, tostring(now))
    redis.call('EXPIRE', key, window)
    return 0
else
    return 1
end`

// localWindow holds the in-process fallback sliding windows.
var (
	localMu      sync.Mutex
	localWindows = map[string][]int64{} // key → slice of unix-nano timestamps
)

func localCheck(key string, limit int, windowSec int64) bool {
	now := time.Now().UnixNano()
	cutoff := now - windowSec*int64(time.Second)
	localMu.Lock()
	defer localMu.Unlock()
	entries := localWindows[key]
	filtered := entries[:0]
	for _, t := range entries {
		if t > cutoff {
			filtered = append(filtered, t)
		}
	}
	if len(filtered) >= limit {
		localWindows[key] = filtered
		return false
	}
	localWindows[key] = append(filtered, now)
	return true
}

func tier(method, path string) string {
	if strings.Contains(path, "/auth/") || strings.HasSuffix(path, "/auth") {
		return "auth"
	}
	m := strings.ToUpper(method)
	if m == "POST" || m == "PUT" || m == "PATCH" || m == "DELETE" {
		return "write"
	}
	return "global"
}

// RateLimit returns a Gin middleware enforcing per-IP rate limits.
// Pass a non-nil redis client to use Redis; nil falls back to in-process.
func RateLimit(rdb *goredis.Client) gin.HandlerFunc {
	script := goredis.NewScript(luaScript)
	return func(c *gin.Context) {
		t := tier(c.Request.Method, c.Request.URL.Path)
		limit := tierLimits[t]
		ip := c.ClientIP()
		key := fmt.Sprintf("rate_limit:%s:%s", t, ip)
		now := time.Now().UnixNano()

		allowed := true
		if rdb != nil {
			ctx, cancel := context.WithTimeout(c.Request.Context(), 300*time.Millisecond)
			defer cancel()
			res, err := script.Run(ctx, rdb, []string{key}, limit, now, int64(60)).Int()
			if err == nil {
				allowed = res == 0
			}
			// On Redis error fall through to in-process
		}
		if rdb == nil {
			allowed = localCheck(key, limit, 60)
		}

		if !allowed {
			c.Header("X-RateLimit-Limit", fmt.Sprintf("%d", limit))
			c.Header("X-RateLimit-Remaining", "0")
			c.AbortWithStatusJSON(http.StatusTooManyRequests, gin.H{
				"error": gin.H{
					"code":    "RATE_LIMIT",
					"message": fmt.Sprintf("Rate limit exceeded (%s: %d/min)", t, limit),
				},
			})
			return
		}
		c.Header("X-RateLimit-Limit", fmt.Sprintf("%d", limit))
		c.Next()
	}
}
