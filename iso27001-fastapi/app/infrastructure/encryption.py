from cryptography.fernet import Fernet
from app.config.settings import settings

class FieldEncryptor:
    """
    A.10: Field-level encryption for PII at rest.
    Uses Fernet (AES-128-CBC with HMAC-SHA256) for symmetric encryption.
    """
    def __init__(self):
        # In production, ensure ENCRYPTION_KEY is loaded from a secrets manager
        try:
            self.fernet = Fernet(settings.ENCRYPTION_KEY)
        except Exception as e:
            raise ValueError(f"Invalid ENCRYPTION_KEY: {e}")

    def encrypt(self, plaintext: str) -> str:
        return self.fernet.encrypt(plaintext.encode()).decode()

    def decrypt(self, ciphertext: str) -> str:
        return self.fernet.decrypt(ciphertext.encode()).decode()