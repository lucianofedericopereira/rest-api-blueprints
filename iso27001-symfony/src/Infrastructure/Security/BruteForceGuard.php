<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

/**
 * A.9: Brute-force login protection.
 *
 * Tracks failed authentication attempts per account (email).
 * Uses Redis (via ext-redis) when available; falls back to APCu,
 * then an in-process array — all silently, no exception propagated.
 *
 * Policy:
 *   MAX_ATTEMPTS = 5  consecutive failures
 *   LOCKOUT_TTL  = 900 seconds (15 minutes)
 *
 * Integration:
 *   - BruteForceSubscriber listens on JWT AuthenticationFailureEvent and
 *     calls record_failure() / is_locked().
 *   - On successful login the LexikJWT AuthenticationSuccessEvent calls clear().
 */
final class BruteForceGuard
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_TTL  = 900; // 15 minutes
    private const KEY_PREFIX   = 'brute_force:';

    /** @var array<string, string> In-process fallback (single-process dev/test) */
    private array $local = [];

    // ── Public API ────────────────────────────────────────────────────────────

    public function isLocked(string $identifier): bool
    {
        $key = self::KEY_PREFIX . $identifier . ':locked_until';

        $value = $this->cacheGet($key);
        if ($value !== null && (float) $value > microtime(true)) {
            return true;
        }

        return false;
    }

    public function recordFailure(string $identifier): void
    {
        $keyCount  = self::KEY_PREFIX . $identifier . ':count';
        $keyLocked = self::KEY_PREFIX . $identifier . ':locked_until';

        $count = (int) ($this->cacheGet($keyCount) ?? 0) + 1;
        $this->cacheSet($keyCount, (string) $count, self::LOCKOUT_TTL);

        if ($count >= self::MAX_ATTEMPTS) {
            $lockedUntil = microtime(true) + self::LOCKOUT_TTL;
            $this->cacheSet($keyLocked, (string) $lockedUntil, self::LOCKOUT_TTL);
            $this->cacheDel($keyCount);
        }
    }

    public function clear(string $identifier): void
    {
        $this->cacheDel(self::KEY_PREFIX . $identifier . ':count');
        $this->cacheDel(self::KEY_PREFIX . $identifier . ':locked_until');
    }

    // ── Storage helpers (Redis → APCu → in-process) ──────────────────────────

    private function cacheGet(string $key): ?string
    {
        if (($r = $this->redis()) !== null) {
            $val = $r->get($key);
            return $val !== false ? (string) $val : null;
        }
        if (function_exists('apcu_fetch')) {
            $success = false;
            $val     = apcu_fetch($key, $success);
            return $success ? (string) $val : null;
        }
        return isset($this->local[$key]) ? (string) $this->local[$key] : null;
    }

    private function cacheSet(string $key, string $value, int $ttl): void
    {
        if (($r = $this->redis()) !== null) {
            $r->setex($key, $ttl, $value);
            return;
        }
        if (function_exists('apcu_store')) {
            apcu_store($key, $value, $ttl);
            return;
        }
        $this->local[$key] = $value;
    }

    private function cacheDel(string $key): void
    {
        if (($r = $this->redis()) !== null) {
            $r->del($key);
            return;
        }
        if (function_exists('apcu_delete')) {
            apcu_delete($key);
            return;
        }
        unset($this->local[$key]);
    }

    private function redis(): ?\Redis
    {
        if (!extension_loaded('redis')) {
            return null;
        }
        $url = $_ENV['REDIS_URL'] ?? getenv('REDIS_URL') ?: null;
        if ($url === null) {
            return null;
        }
        try {
            $parsed = parse_url($url);
            $host   = (string) ($parsed['host'] ?? '127.0.0.1');
            $port   = (int)    ($parsed['port'] ?? 6379);
            $r      = new \Redis();
            $r->connect($host, $port, 0.5);
            if (isset($parsed['pass']) && $parsed['pass'] !== '') {
                $r->auth($parsed['pass']);
            }
            return $r;
        } catch (\Throwable) {
            return null;
        }
    }
}
