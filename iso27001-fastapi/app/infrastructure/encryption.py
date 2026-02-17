import base64
import os
from cryptography.hazmat.primitives.ciphers.aead import AESGCM  # type: ignore[import-not-found]
from app.config.settings import settings


class FieldEncryptor:
    """
    A.10: Field-level encryption for PII at rest.
    Uses AES-256-GCM — authenticated encryption with a 12-byte random IV
    and a 16-byte authentication tag, matching the Symfony/Laravel implementations.

    Key: exactly 32 bytes derived from the ENCRYPTION_KEY setting (UTF-8).
    Wire format (base64): IV (12 bytes) + TAG (16 bytes) + ciphertext.
    """

    _IV_LENGTH = 12
    _TAG_LENGTH = 16

    def __init__(self) -> None:
        # In production, ensure ENCRYPTION_KEY is loaded from a secrets manager
        key_bytes = settings.ENCRYPTION_KEY.encode("utf-8")
        if len(key_bytes) != 32:
            raise ValueError(
                f"ENCRYPTION_KEY must be exactly 32 bytes when UTF-8 encoded, got {len(key_bytes)}"
            )
        self._aesgcm = AESGCM(key_bytes)

    def encrypt(self, plaintext: str) -> str:
        iv = os.urandom(self._IV_LENGTH)
        # AESGCM.encrypt appends the 16-byte tag to the ciphertext automatically
        ciphertext_with_tag = self._aesgcm.encrypt(iv, plaintext.encode("utf-8"), None)
        return base64.b64encode(iv + ciphertext_with_tag).decode("ascii")

    def decrypt(self, ciphertext: str) -> str:
        raw = base64.b64decode(ciphertext.encode("ascii"))
        iv = raw[: self._IV_LENGTH]
        ciphertext_with_tag = raw[self._IV_LENGTH :]
        result: bytes = self._aesgcm.decrypt(iv, ciphertext_with_tag, None)
        return result.decode("utf-8")