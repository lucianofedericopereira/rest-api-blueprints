import uuid
from fastapi import APIRouter, Depends
from fastapi.security import OAuth2PasswordRequestForm
from sqlalchemy.orm import Session
from app.core.database import get_db
from app.domain.users.repository import UserRepository
from app.config.security import verify_password, create_token_pair, TokenPair
from app.core.exceptions import AuthenticationError
from app.core.telemetry import logger

router = APIRouter()

@router.post("/token", response_model=TokenPair)
def login(
    form_data: OAuth2PasswordRequestForm = Depends(),
    db: Session = Depends(get_db)
):
    """A.9: Authenticate user and issue JWTs."""
    repo = UserRepository(db)
    user = repo.get_by_email(form_data.username)
    
    if not user or not verify_password(form_data.password, user.hashed_password):
        logger.warning("auth.failed", email=form_data.username)
        raise AuthenticationError("Invalid credentials")
    
    if not user.is_active:
        raise AuthenticationError("User inactive")

    logger.audit("auth.login", user_id=user.id)
    return create_token_pair(
        user_id=user.id,
        role=user.role,
        access_jti=str(uuid.uuid4()),
        refresh_jti=str(uuid.uuid4())
    )