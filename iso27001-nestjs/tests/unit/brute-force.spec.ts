/**
 * Unit tests for BruteForceGuard (in-process fallback — no Redis).
 * Mirrors iso27001-fastapi/tests/unit/test_rate_limiter.py brute-force cases.
 */
import { BruteForceGuard } from '../../src/infrastructure/security/brute-force.guard';
import { HttpException } from '@nestjs/common';

describe('BruteForceGuard — in-process fallback (A.9)', () => {
  let guard: BruteForceGuard;

  beforeEach(() => {
    guard = new BruteForceGuard(null); // null Redis → in-process fallback
  });

  it('allows requests when account is clean', async () => {
    await expect(guard.check('user@example.com')).resolves.not.toThrow();
  });

  it('does not lock after fewer than 5 failures', async () => {
    for (let i = 0; i < 4; i++) {
      await guard.recordFailure('user@example.com');
    }
    await expect(guard.check('user@example.com')).resolves.not.toThrow();
  });

  it('locks account after 5 consecutive failures', async () => {
    for (let i = 0; i < 5; i++) {
      await guard.recordFailure('user@example.com');
    }
    await expect(guard.check('user@example.com')).rejects.toThrow(HttpException);
  });

  it('locked account throws 429', async () => {
    for (let i = 0; i < 5; i++) {
      await guard.recordFailure('locked@example.com');
    }
    try {
      await guard.check('locked@example.com');
      fail('expected HttpException');
    } catch (err) {
      expect(err).toBeInstanceOf(HttpException);
      expect((err as HttpException).getStatus()).toBe(429);
    }
  });

  it('different identifiers are independent', async () => {
    for (let i = 0; i < 5; i++) {
      await guard.recordFailure('userA@example.com');
    }
    // userA is locked; userB must still be allowed
    await expect(guard.check('userB@example.com')).resolves.not.toThrow();
  });

  it('clear() unlocks a locked account', async () => {
    for (let i = 0; i < 5; i++) {
      await guard.recordFailure('user@example.com');
    }
    await guard.clear('user@example.com');
    await expect(guard.check('user@example.com')).resolves.not.toThrow();
  });

  it('error response body contains ACCOUNT_LOCKED code', async () => {
    for (let i = 0; i < 5; i++) {
      await guard.recordFailure('locked2@example.com');
    }
    try {
      await guard.check('locked2@example.com');
    } catch (err) {
      const body = (err as HttpException).getResponse() as Record<string, string>;
      expect(body['code']).toBe('ACCOUNT_LOCKED');
    }
  });
});
