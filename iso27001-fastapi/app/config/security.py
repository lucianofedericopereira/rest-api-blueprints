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

ACCESS_TOKEN_TYP = "access"
REFRESH_TOKEN_TYP = "refresh"

class TokenPayload(BaseModel):
    sub: str
    exp: int
    role: str = ""
    jti: str = ""
    typ: str = ""

def verify_password(plain_password: str, hashed_password: str) -> bool:
    return pwd_context.verify(plain_password, hashed_password)

def hash_password(password: str) -> str:
    return pwd_context.hash(password)

def create_access_token(
    subject: str | Any,
    role: str = "",
    jti: str = "",
    expires_delta: timedelta | None = None,
    typ: str = ACCESS_TOKEN_TYP,
) -> str:
    if expires_delta:
        expire = datetime.now(timezone.utc) + expires_delta
    else:
        expire = datetime.now(timezone.utc) + timedelta(minutes=settings.JWT_ACCESS_TOKEN_EXPIRE_MINUTES)

    # A.9: typ separates access vs refresh tokens so an access token cannot be
    # presented at /refresh to extend a session beyond its short TTL.
    to_encode: dict[str, Any] = {"sub": str(subject), "exp": expire, "typ": typ}
    if role:
        to_encode["role"] = role   # A.9: embed RBAC claim so no DB lookup needed
    if jti:
        to_encode["jti"] = jti     # A.9: JWT ID for replay protection / revocation
    return jwt.encode(to_encode, settings.JWT_SECRET_KEY, algorithm=settings.JWT_ALGORITHM)

def create_token_pair(user_id: str, role: str, access_jti: str, refresh_jti: str) -> TokenPair:
    # A.9: Short-lived access token with role claim and JTI
    access_token = create_access_token(
        user_id, role=role, jti=access_jti, typ=ACCESS_TOKEN_TYP
    )

    # A.9: Long-lived refresh token — different JTI for revocation tracking
    refresh_expires = timedelta(days=settings.JWT_REFRESH_TOKEN_EXPIRE_DAYS)
    refresh_token = create_access_token(
        user_id, role=role, jti=refresh_jti, expires_delta=refresh_expires, typ=REFRESH_TOKEN_TYP
    )

    return TokenPair(access_token=access_token, refresh_token=refresh_token)

def decode_token(token: str, expected_typ: str | None = None) -> TokenPayload:
    """
    Decodes and validates the JWT.

    When ``expected_typ`` is provided, the token's ``typ`` claim must match
    exactly. This prevents an access token from being accepted at /refresh
    and a refresh token from being used as bearer credentials (A.9.4).

    Raises jwt.PyJWTError if invalid, expired, or the typ does not match.
    """
    payload = jwt.decode(token, settings.JWT_SECRET_KEY, algorithms=[settings.JWT_ALGORITHM])
    parsed = TokenPayload(**payload)
    if expected_typ is not None and parsed.typ != expected_typ:
        raise jwt.InvalidTokenError(
            f"unexpected token typ: got {parsed.typ!r}, expected {expected_typ!r}"
        )
    return parsed