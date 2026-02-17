from datetime import datetime, timedelta, timezone
from typing import Any
import jwt
from passlib.context import CryptContext
from pydantic import BaseModel
from app.config.settings import settings

pwd_context = CryptContext(schemes=["bcrypt"], deprecated="auto")

class TokenPair(BaseModel):
    access_token: str
    refresh_token: str
    token_type: str = "bearer"

class TokenPayload(BaseModel):
    sub: str
    exp: int
    role: str = ""
    jti: str = ""

def verify_password(plain_password: str, hashed_password: str) -> bool:
    return pwd_context.verify(plain_password, hashed_password)

def hash_password(password: str) -> str:
    return pwd_context.hash(password)

def create_access_token(
    subject: str | Any,
    role: str = "",
    jti: str = "",
    expires_delta: timedelta | None = None,
) -> str:
    if expires_delta:
        expire = datetime.now(timezone.utc) + expires_delta
    else:
        expire = datetime.now(timezone.utc) + timedelta(minutes=settings.JWT_ACCESS_TOKEN_EXPIRE_MINUTES)

    to_encode: dict[str, Any] = {"sub": str(subject), "exp": expire}
    if role:
        to_encode["role"] = role   # A.9: embed RBAC claim so no DB lookup needed
    if jti:
        to_encode["jti"] = jti     # A.9: JWT ID for replay protection / revocation
    return jwt.encode(to_encode, settings.JWT_SECRET_KEY, algorithm=settings.JWT_ALGORITHM)

def create_token_pair(user_id: str, role: str, access_jti: str, refresh_jti: str) -> TokenPair:
    # A.9: Short-lived access token with role claim and JTI
    access_token = create_access_token(user_id, role=role, jti=access_jti)

    # A.9: Long-lived refresh token — different JTI for revocation tracking
    refresh_expires = timedelta(days=settings.JWT_REFRESH_TOKEN_EXPIRE_DAYS)
    refresh_token = create_access_token(
        user_id, role=role, jti=refresh_jti, expires_delta=refresh_expires
    )

    return TokenPair(access_token=access_token, refresh_token=refresh_token)

def decode_token(token: str) -> TokenPayload:
    """
    Decodes and validates the JWT.
    Raises jwt.PyJWTError if invalid or expired.
    """
    payload = jwt.decode(token, settings.JWT_SECRET_KEY, algorithms=[settings.JWT_ALGORITHM])
    return TokenPayload(**payload)