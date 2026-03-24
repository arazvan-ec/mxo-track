import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { computeSignature } from '../src/transport/webhook-handler.js';

describe('webhook-handler', () => {
  describe('computeSignature', () => {
    it('produces a valid sha256 HMAC signature', () => {
      const secret = 'test-secret';
      const body = '{"ref":"refs/heads/main"}';
      const sig = computeSignature(secret, body);

      expect(sig).toMatch(/^sha256=[a-f0-9]{64}$/);
    });

    it('produces different signatures for different secrets', () => {
      const body = '{"ref":"refs/heads/main"}';
      const sig1 = computeSignature('secret-1', body);
      const sig2 = computeSignature('secret-2', body);

      expect(sig1).not.toBe(sig2);
    });

    it('produces different signatures for different bodies', () => {
      const secret = 'test-secret';
      const sig1 = computeSignature(secret, '{"ref":"refs/heads/main"}');
      const sig2 = computeSignature(secret, '{"ref":"refs/heads/develop"}');

      expect(sig1).not.toBe(sig2);
    });

    it('is deterministic', () => {
      const secret = 'test-secret';
      const body = '{"ref":"refs/heads/main"}';
      const sig1 = computeSignature(secret, body);
      const sig2 = computeSignature(secret, body);

      expect(sig1).toBe(sig2);
    });
  });
});
