<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Illuminate\Http\Response;

/**
 * A.9: Brute-force login protection.
 *
 * Tracks failed authentication attempts per account (email).
 * Uses Laravel's Cache facade (Redis / file / array depending on config);
 * falls back silently if the cache store is unavailable.
 *
 * Policy:
 *   MAX_ATTEMPTS = 5  consecutive failures
 *   LOCKOUT_TTL  = 900 seconds (15 minutes)
 *
 * Integration:
 *   AuthController::login() calls check(), recordFailure(), and clear().
 */
final class BruteForceGuard
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_TTL  = 900; // 15 minutes
    private const KEY_PREFIX   = 'brute_force:';

    public function isLocked(string $identifier): bool
    {
        try {
            $lockedUntil = \Illuminate\Support\Facades\Cache::get(
                self::KEY_PREFIX . $identifier . ':locked_until'
            );
            return $lockedUntil !== null && (float) $lockedUntil > microtime(true);
        } catch (\Throwable) {
            return false;
        }
    }

    public function recordFailure(string $identifier): void
    {
        try {
            $keyCount  = self::KEY_PREFIX . $identifier . ':count';
            $keyLocked = self::KEY_PREFIX . $identifier . ':locked_until';
            $ttl       = self::LOCKOUT_TTL;

            $count = (int) (\Illuminate\Support\Facades\Cache::get($keyCount) ?? 0) + 1;
            \Illuminate\Support\Facades\Cache::put($keyCount, $count, $ttl);

            if ($count >= self::MAX_ATTEMPTS) {
                $lockedUntil = microtime(true) + $ttl;
                \Illuminate\Support\Facades\Cache::put($keyLocked, $lockedUntil, $ttl);
                \Illuminate\Support\Facades\Cache::forget($keyCount);
            }
        } catch (\Throwable) {
            // Cache failure must never crash the application
        }
    }

    public function clear(string $identifier): void
    {
        try {
            \Illuminate\Support\Facades\Cache::forget(self::KEY_PREFIX . $identifier . ':count');
            \Illuminate\Support\Facades\Cache::forget(self::KEY_PREFIX . $identifier . ':locked_until');
        } catch (\Throwable) {
        }
    }
}
