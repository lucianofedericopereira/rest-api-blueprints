import { Injectable, HttpException, HttpStatus } from '@nestjs/common';
import { Redis } from 'ioredis';

/**
 * A.9: Brute-force login protection.
 *
 * Tracks failed authentication attempts per account identifier (email).
 * Uses Redis when available (cross-process, survives restarts); falls back to
 * an in-process Map for dev/test environments without Redis.
 *
 * Policy:
 *   MAX_ATTEMPTS  : 5 consecutive failures trigger a lockout
 *   LOCKOUT_TTL   : 15 minutes (900 seconds)
 *   Window resets on a successful login (clear())
 */
@Injectable()
export class BruteForceGuard {
  private static readonly MAX_ATTEMPTS = 5;
  private static readonly LOCKOUT_TTL = 900; // seconds
  private static readonly KEY_PREFIX = 'brute_force:';

  // In-process fallback: { identifier -> { count, lockedUntil (epoch ms) } }
  private readonly local = new Map<string, { count: number; lockedUntil: number }>();

  constructor(private readonly redis: Redis | null) {}

  /** Throw HTTP 429 if the account is currently locked. */
  async check(identifier: string): Promise<void> {
    if (this.redis) {
      const lockedUntil = await this.redis.get(`${BruteForceGuard.KEY_PREFIX}${identifier}:locked_until`);
      if (lockedUntil && parseFloat(lockedUntil) > Date.now() / 1000) {
        this.throwLocked();
      }
    } else {
      const entry = this.local.get(identifier);
      if (entry && entry.lockedUntil > Date.now()) {
        this.throwLocked();
      }
    }
  }

  /** Increment failure counter; lock the account on threshold breach. */
  async recordFailure(identifier: string): Promise<void> {
    if (this.redis) {
      const countKey = `${BruteForceGuard.KEY_PREFIX}${identifier}:count`;
      const lockKey = `${BruteForceGuard.KEY_PREFIX}${identifier}:locked_until`;
      const count = await this.redis.incr(countKey);
      await this.redis.expire(countKey, BruteForceGuard.LOCKOUT_TTL);
      if (count >= BruteForceGuard.MAX_ATTEMPTS) {
        const lockedUntil = Math.floor(Date.now() / 1000) + BruteForceGuard.LOCKOUT_TTL;
        await this.redis.set(lockKey, lockedUntil, 'EX', BruteForceGuard.LOCKOUT_TTL);
        await this.redis.del(countKey);
      }
    } else {
      const entry = this.local.get(identifier) ?? { count: 0, lockedUntil: 0 };
      entry.count += 1;
      if (entry.count >= BruteForceGuard.MAX_ATTEMPTS) {
        entry.lockedUntil = Date.now() + BruteForceGuard.LOCKOUT_TTL * 1000;
        entry.count = 0;
      }
      this.local.set(identifier, entry);
    }
  }

  /** Clear failure counters after a successful login. */
  async clear(identifier: string): Promise<void> {
    if (this.redis) {
      await this.redis.del(
        `${BruteForceGuard.KEY_PREFIX}${identifier}:count`,
        `${BruteForceGuard.KEY_PREFIX}${identifier}:locked_until`,
      );
    } else {
      this.local.delete(identifier);
    }
  }

  private throwLocked(): never {
    throw new HttpException(
      { code: 'ACCOUNT_LOCKED', message: 'Too many failed attempts. Account temporarily locked.' },
      HttpStatus.TOO_MANY_REQUESTS,
    );
  }
}
