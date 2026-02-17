import uuid
import jwt
from fastapi import APIRouter, Depends
from fastapi.security import OAuth2PasswordRequestForm
from pydantic import BaseModel
from sqlalchemy.orm import Session
from app.core.database import get_db
from app.domain.users.repository import UserRepository
from app.config.security import verify_password, create_token_pair, decode_token, TokenPair
from app.core.exceptions import AuthenticationError
from app.core.telemetry import logger
from app.core.brute_force import brute_force_guard

router = APIRouter()


class RefreshRequest(BaseModel):
    refresh_token: str


@router.post("/token", response_model=TokenPair)
def login(
    form_data: OAuth2PasswordRequestForm = Depends(),
    db: Session = Depends(get_db),
) -> TokenPair:
    """A.9: Authenticate user and issue JWTs. Brute-force protected."""
    email = form_data.username
    brute_force_guard.check(email)  # raises HTTP 429 if account is locked

    repo = UserRepository(db)
    user = repo.get_by_email(email)

    if not user or not verify_password(form_data.password, str(user.hashed_password)):
        brute_force_guard.record_failure(email)
        logger.warning("auth.failed", email=email)
        raise AuthenticationError("Invalid credentials")

    if not user.is_active:
        raise AuthenticationError("User inactive")

    brute_force_guard.clear(email)
    logger.audit("auth.login", user_id=str(user.id))
    return create_token_pair(
        user_id=str(user.id),
        role=str(user.role),
        access_jti=str(uuid.uuid4()),
        refresh_jti=str(uuid.uuid4()),
    )


@router.post("/refresh", response_model=TokenPair)
def refresh(
    body: RefreshRequest,
    db: Session = Depends(get_db),
) -> TokenPair:
    """A.9: Exchange a valid refresh token for a new token pair."""
    try:
        payload = decode_token(body.refresh_token)
    except jwt.PyJWTError:
        raise AuthenticationError("Invalid or expired refresh token")

    repo = UserRepository(db)
    user = repo.get_by_id(payload.sub)
    if user is None or not user.is_active:
        raise AuthenticationError("User not found or inactive")

    logger.audit("auth.refresh", user_id=str(user.id))
    return create_token_pair(
        user_id=str(user.id),
        role=str(user.role),
        access_jti=str(uuid.uuid4()),
        refresh_jti=str(uuid.uuid4()),
    )
