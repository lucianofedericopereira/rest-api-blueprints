defmodule Iso27001Phoenix.Infrastructure.Encryption.FieldEncryptor do
  @moduledoc """
  A.10: AES-256-GCM field-level encryption for PII at rest.

  Key:         32 bytes from ENCRYPTION_KEY env var
  IV:          12 random bytes per encrypt call
  Auth tag:    16 bytes (GCM default)
  Wire format: Base64(IV ++ TAG ++ ciphertext)
  """

  @iv_length  12
  @tag_length 16

  @doc "Encrypts plaintext string. Returns Base64-encoded ciphertext."
  def encrypt(plaintext) when is_binary(plaintext) do
    key = encryption_key!()
    iv  = :crypto.strong_rand_bytes(@iv_length)

    {ciphertext, tag} =
      :crypto.crypto_one_time_aead(:aes_256_gcm, key, iv, plaintext, "", true)

    Base.encode64(iv <> tag <> ciphertext)
  end

  @doc "Decrypts Base64-encoded ciphertext. Returns {:ok, plaintext} or {:error, reason}."
  def decrypt(encoded) when is_binary(encoded) do
    key = encryption_key!()
    raw = Base.decode64!(encoded)

    <<iv::binary-size(@iv_length), tag::binary-size(@tag_length), ciphertext::binary>> = raw

    case :crypto.crypto_one_time_aead(:aes_256_gcm, key, iv, ciphertext, "", tag, false) do
      plaintext when is_binary(plaintext) -> {:ok, plaintext}
      :error -> {:error, :decryption_failed}
    end
  rescue
    _ -> {:error, :decryption_failed}
  end

  defp encryption_key! do
    key = Application.get_env(:iso27001_phoenix, :encryption_key, "")
    unless byte_size(key) == 32 do
      raise "ENCRYPTION_KEY must be exactly 32 bytes, got #{byte_size(key)}"
    end
    key
  end
end
