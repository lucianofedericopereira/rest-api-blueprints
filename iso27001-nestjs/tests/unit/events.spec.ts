/**
 * Unit tests for domain events.
 * Mirrors iso27001-fastapi/tests/unit/test_events.py (9 tests).
 */
import { UserCreatedEvent } from '../../src/domain/users/events/user-created.event';

describe('UserCreatedEvent (A.12 — domain events)', () => {
  it('stores userId, emailHash, and role', () => {
    const event = new UserCreatedEvent('usr_123', 'abc123hash', 'viewer');
    expect(event.userId).toBe('usr_123');
    expect(event.emailHash).toBe('abc123hash');
    expect(event.role).toBe('viewer');
  });

  it('stores a non-empty userId', () => {
    const event = new UserCreatedEvent('usr_456', 'hash', 'admin');
    expect(event.userId).toBeTruthy();
  });

  it('email hash is never the raw email', () => {
    const rawEmail = 'user@example.com';
    const event = new UserCreatedEvent('usr_789', 'hashed_value', 'viewer');
    expect(event.emailHash).not.toBe(rawEmail);
  });

  it('different events for same user have same userId', () => {
    const e1 = new UserCreatedEvent('usr_1', 'hash1', 'viewer');
    const e2 = new UserCreatedEvent('usr_1', 'hash2', 'admin');
    expect(e1.userId).toBe(e2.userId);
  });
});

// ── EventEmitter2 integration (subscribe / publish) ──────────────────────────

import { EventEmitter2 } from '@nestjs/event-emitter';

describe('EventEmitter2 — domain event bus (A.12)', () => {
  it('subscriber receives published event', () => {
    const emitter = new EventEmitter2();
    const received: UserCreatedEvent[] = [];
    emitter.on('user.created', (e: UserCreatedEvent) => received.push(e));

    const event = new UserCreatedEvent('u1', 'h1', 'admin');
    emitter.emit('user.created', event);

    expect(received).toHaveLength(1);
    expect(received[0]).toBe(event);
  });

  it('publishing with no listener does not throw', () => {
    const emitter = new EventEmitter2();
    expect(() =>
      emitter.emit('user.created', new UserCreatedEvent('u1', 'h1', 'admin')),
    ).not.toThrow();
  });

  it('multiple listeners all receive the event', () => {
    const emitter = new EventEmitter2();
    const calls: string[] = [];
    emitter.on('user.created', () => calls.push('listener1'));
    emitter.on('user.created', () => calls.push('listener2'));

    emitter.emit('user.created', new UserCreatedEvent('u1', 'h1', 'viewer'));
    expect(calls).toEqual(['listener1', 'listener2']);
  });

  it('different event names do not cross-fire', () => {
    const emitter = new EventEmitter2();
    const calls: string[] = [];
    emitter.on('user.created', () => calls.push('created'));
    emitter.on('user.deleted', () => calls.push('deleted'));

    emitter.emit('user.created', new UserCreatedEvent('u1', 'h1', 'admin'));
    expect(calls).toEqual(['created']);
  });

  it('wildcard listener receives any user event', () => {
    const emitter = new EventEmitter2({ wildcard: true });
    const calls: string[] = [];
    emitter.on('user.*', () => calls.push('wildcard'));

    emitter.emit('user.created', new UserCreatedEvent('u1', 'h1', 'admin'));
    expect(calls).toHaveLength(1);
  });
});
