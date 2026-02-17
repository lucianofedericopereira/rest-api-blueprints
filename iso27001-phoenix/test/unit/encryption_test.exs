defmodule Iso27001Phoenix.EncryptionTest do
  use ExUnit.Case, async: true

  alias Iso27001Phoenix.Infrastructure.Encryption.FieldEncryptor

  # Set 32-byte key in application env before tests
  setup do
    Application.put_env(:iso27001_phoenix, :encryption_key, "test-only-key-exactly-32-bytes!!")
    :ok
  end

  test "round-trip encrypt/decrypt returns original plaintext" do
    plaintext  = "user@example.com"
    ciphertext = FieldEncryptor.encrypt(plaintext)
    assert {:ok, ^plaintext} = FieldEncryptor.decrypt(ciphertext)
  end

  test "each encryption produces a different ciphertext (unique IV)" do
    plaintext = "same-input"
    c1 = FieldEncryptor.encrypt(plaintext)
    c2 = FieldEncryptor.encrypt(plaintext)
    refute c1 == c2
  end

  test "wrong key length raises at encrypt time" do
    Application.put_env(:iso27001_phoenix, :encryption_key, "too-short")
    assert_raise RuntimeError, ~r/32 bytes/, fn ->
      FieldEncryptor.encrypt("anything")
    end
  after
    Application.put_env(:iso27001_phoenix, :encryption_key, "test-only-key-exactly-32-bytes!!")
  end

  test "tampered ciphertext returns decryption error" do
    ciphertext = FieldEncryptor.encrypt("hello")
    tampered   = ciphertext <> "X"
    assert {:error, :decryption_failed} = FieldEncryptor.decrypt(tampered)
  end
end
