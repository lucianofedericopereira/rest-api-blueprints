"""Unit tests for JWT security utilities."""
import uuid
import pytest
import jwt

from app.config.security import (
    create_access_token,
    create_token_pair,
    decode_token,
    hash_password,
    verify_password,
)
from app.config.settings import settings


class TestPasswordHashing:
    def test_hash_and_verify(self):
        plain = "SecurePassword123!"
        hashed = hash_password(plain)
        assert hashed != plain
        assert verify_password(plain, hashed)

    def test_wrong_password_fails(self):
        hashed = hash_password("correct")
        assert not verify_password("wrong", hashed)


class TestJWTTokens:
    def test_create_access_token_contains_role(self):
        token = create_access_token("usr_123", role="admin", jti="jti_abc")
        payload = jwt.decode(token, settings.JWT_SECRET_KEY, algorithms=[settings.JWT_ALGORITHM])
        assert payload["sub"] == "usr_123"
        assert payload["role"] == "admin"
        assert payload["jti"] == "jti_abc"

    def test_decode_token_round_trip(self):
        token = create_access_token("usr_456", role="viewer", jti="jti_xyz")
        decoded = decode_token(token)
        assert decoded.sub == "usr_456"
        assert decoded.role == "viewer"
        assert decoded.jti == "jti_xyz"

    def test_create_token_pair_returns_both_tokens(self):
        pair = create_token_pair(
            user_id="usr_789",
            role="manager",
            access_jti=str(uuid.uuid4()),
            refresh_jti=str(uuid.uuid4()),
        )
        assert pair.access_token
        assert pair.refresh_token
        assert pair.token_type == "bearer"
        assert pair.access_token != pair.refresh_token

    def test_token_pair_different_jtis(self):
        access_jti = str(uuid.uuid4())
        refresh_jti = str(uuid.uuid4())
        pair = create_token_pair("usr_1", "admin", access_jti, refresh_jti)

        access_payload = jwt.decode(pair.access_token, settings.JWT_SECRET_KEY, algorithms=[settings.JWT_ALGORITHM])
        refresh_payload = jwt.decode(pair.refresh_token, settings.JWT_SECRET_KEY, algorithms=[settings.JWT_ALGORITHM])

        assert access_payload["jti"] == access_jti
        assert refresh_payload["jti"] == refresh_jti
        assert access_payload["jti"] != refresh_payload["jti"]
