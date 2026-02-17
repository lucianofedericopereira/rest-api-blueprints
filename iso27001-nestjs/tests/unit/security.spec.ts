/**
 * Unit tests for JWT security utilities and bcrypt hashing.
 * Mirrors iso27001-fastapi/tests/unit/test_security.py (6 tests).
 */
import * as bcrypt from 'bcrypt';
import * as jwt from 'jsonwebtoken';
import type { StringValue } from 'ms';

const JWT_SECRET = 'test-secret-key';
const JWT_ALGORITHM = 'HS256';

function createAccessToken(
  userId: string,
  role: string,
  jti: string,
  expiresIn: StringValue = '30m',
): string {
  return jwt.sign({ sub: userId, role, jti, type: 'access' }, JWT_SECRET, {
    algorithm: JWT_ALGORITHM,
    expiresIn,
  });
}

function createRefreshToken(
  userId: string,
  role: string,
  jti: string,
  expiresIn: StringValue = '7d',
): string {
  return jwt.sign({ sub: userId, role, jti, type: 'refresh' }, JWT_SECRET, {
    algorithm: JWT_ALGORITHM,
    expiresIn,
  });
}

function decodeToken(token: string): jwt.JwtPayload {
  return jwt.verify(token, JWT_SECRET, { algorithms: [JWT_ALGORITHM] }) as jwt.JwtPayload;
}

// ── Password hashing ──────────────────────────────────────────────────────────

describe('Password hashing (A.10 — bcrypt)', () => {
  it('hashes and verifies a correct password', async () => {
    const plain = 'SecurePassword123!';
    const hashed = await bcrypt.hash(plain, 12);
    expect(hashed).not.toBe(plain);
    expect(await bcrypt.compare(plain, hashed)).toBe(true);
  });

  it('rejects a wrong password', async () => {
    const hashed = await bcrypt.hash('correct', 12);
    expect(await bcrypt.compare('wrong', hashed)).toBe(false);
  });
});

// ── JWT tokens ────────────────────────────────────────────────────────────────

describe('JWT access token (A.9)', () => {
  it('contains sub, role, jti, and type claims', () => {
    const token = createAccessToken('usr_123', 'admin', 'jti_abc');
    const payload = decodeToken(token);
    expect(payload['sub']).toBe('usr_123');
    expect(payload['role']).toBe('admin');
    expect(payload['jti']).toBe('jti_abc');
    expect(payload['type']).toBe('access');
  });

  it('round-trips through decode correctly', () => {
    const token = createAccessToken('usr_456', 'viewer', 'jti_xyz');
    const payload = decodeToken(token);
    expect(payload['sub']).toBe('usr_456');
    expect(payload['role']).toBe('viewer');
    expect(payload['jti']).toBe('jti_xyz');
  });
});

describe('JWT token pair (A.9)', () => {
  it('produces different access and refresh tokens', () => {
    const accessJti = 'access-jti-001';
    const refreshJti = 'refresh-jti-001';
    const access = createAccessToken('usr_789', 'manager', accessJti);
    const refresh = createRefreshToken('usr_789', 'manager', refreshJti);
    expect(access).not.toBe(refresh);
  });

  it('access and refresh tokens have different jtis', () => {
    const accessJti = 'access-jti-002';
    const refreshJti = 'refresh-jti-002';
    const access = createAccessToken('usr_1', 'admin', accessJti);
    const refresh = createRefreshToken('usr_1', 'admin', refreshJti);
    const ap = decodeToken(access);
    const rp = decodeToken(refresh);
    expect(ap['jti']).toBe(accessJti);
    expect(rp['jti']).toBe(refreshJti);
    expect(ap['jti']).not.toBe(rp['jti']);
  });
});
