<?php

declare(strict_types=1);

namespace App\Infrastructure\Encryption;

/**
 * A.10: AES-256-GCM field-level encryption for PII at rest.
 * Used for encrypting email and phone fields in the database.
 * Key is loaded from environment / secrets manager — never hardcoded.
 */
final class FieldEncryptor
{
    private const CIPHER = 'aes-256-gcm';
    private const TAG_LENGTH = 16;

    public function __construct(private readonly string $key)
    {
        if (strlen($key) !== 32) {
            throw new \InvalidArgumentException(
                'Encryption key must be exactly 32 bytes for AES-256.'
            );
        }
    }

    /**
     * Encrypts plaintext.
     * Returns base64-encoded: IV (12 bytes) + TAG (16 bytes) + ciphertext
     */
    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12); // 96-bit IV for GCM
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH,
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypts a value produced by encrypt().
     */
    public function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, strict: true);
        if ($raw === false) {
            throw new \InvalidArgumentException('Invalid base64 ciphertext.');
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, self::TAG_LENGTH);
        $ciphertext = substr($raw, 12 + self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed — data may be tampered.');
        }

        return $plaintext;
    }
}
