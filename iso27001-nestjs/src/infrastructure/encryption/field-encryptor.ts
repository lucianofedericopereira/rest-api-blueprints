import * as crypto from 'crypto';

/**
 * A.10: Field-level encryption for PII at rest.
 * Uses AES-256-GCM — authenticated encryption with a 12-byte random IV
 * and a 16-byte authentication tag, matching the FastAPI/Symfony/Laravel implementations.
 *
 * Key: exactly 32 bytes derived from the ENCRYPTION_KEY env var (UTF-8).
 * Wire format (base64): IV (12 bytes) + TAG (16 bytes) + ciphertext.
 */
export class FieldEncryptor {
  private static readonly IV_LENGTH = 12;
  private static readonly TAG_LENGTH = 16;
  private static readonly ALGORITHM = 'aes-256-gcm';

  private readonly key: Buffer;

  constructor(encryptionKey: string) {
    const keyBuffer = Buffer.from(encryptionKey, 'utf-8');
    if (keyBuffer.length !== 32) {
      throw new Error(
        `ENCRYPTION_KEY must be exactly 32 bytes when UTF-8 encoded, got ${keyBuffer.length}`,
      );
    }
    this.key = keyBuffer;
  }

  encrypt(plaintext: string): string {
    const iv = crypto.randomBytes(FieldEncryptor.IV_LENGTH);
    const cipher = crypto.createCipheriv(FieldEncryptor.ALGORITHM, this.key, iv);
    const encrypted = Buffer.concat([cipher.update(plaintext, 'utf-8'), cipher.final()]);
    const tag = cipher.getAuthTag();
    // Wire format: IV + TAG + ciphertext
    return Buffer.concat([iv, tag, encrypted]).toString('base64');
  }

  decrypt(ciphertext: string): string {
    const raw = Buffer.from(ciphertext, 'base64');
    const iv = raw.subarray(0, FieldEncryptor.IV_LENGTH);
    const tag = raw.subarray(FieldEncryptor.IV_LENGTH, FieldEncryptor.IV_LENGTH + FieldEncryptor.TAG_LENGTH);
    const encrypted = raw.subarray(FieldEncryptor.IV_LENGTH + FieldEncryptor.TAG_LENGTH);
    const decipher = crypto.createDecipheriv(FieldEncryptor.ALGORITHM, this.key, iv);
    decipher.setAuthTag(tag);
    return decipher.update(encrypted) + decipher.final('utf-8');
  }
}
