<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\Encryption\FieldEncryptor;
use PHPUnit\Framework\TestCase;

class FieldEncryptorTest extends TestCase
{
    private FieldEncryptor $encryptor;

    protected function setUp(): void
    {
        // A.10: 32-byte key for AES-256
        $this->encryptor = new FieldEncryptor('test-key-exactly-32bytes-long!!!');
    }

    public function test_encrypt_and_decrypt_round_trip(): void
    {
        $plaintext  = 'user@example.com';
        $ciphertext = $this->encryptor->encrypt($plaintext);

        $this->assertNotSame($plaintext, $ciphertext);
        $this->assertSame($plaintext, $this->encryptor->decrypt($ciphertext));
    }

    public function test_same_plaintext_produces_different_ciphertexts(): void
    {
        // Each call uses a random IV — same input should not produce same output
        $c1 = $this->encryptor->encrypt('same');
        $c2 = $this->encryptor->encrypt('same');

        $this->assertNotSame($c1, $c2);
        $this->assertSame('same', $this->encryptor->decrypt($c1));
        $this->assertSame('same', $this->encryptor->decrypt($c2));
    }

    public function test_invalid_key_length_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('32 bytes');

        new FieldEncryptor('short-key');
    }

    public function test_tampered_ciphertext_throws(): void
    {
        $ciphertext = $this->encryptor->encrypt('sensitive');
        $tampered   = base64_encode('tampered_data_____________________garbage');

        $this->expectException(\RuntimeException::class);

        $this->encryptor->decrypt($tampered);
    }
}
