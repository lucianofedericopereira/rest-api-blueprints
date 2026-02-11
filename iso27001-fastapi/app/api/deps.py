from typing import Annotated
from fastapi import Depends, HTTPException, status
from fastapi.security import OAuth2PasswordBearer
from sqlalchemy.orm import Session
from app.core.database import get_db
from app.config.security import decode_token
from app.core.exceptions import AuthenticationError
from app.domain.users.repository import UserRepository
from app.domain.users.service import UserService
from app.domain.users.models import User

oauth2_scheme = OAuth2PasswordBearer(tokenUrl="/api/v1/auth/token")

def get_repository(db: Session = Depends(get_db)) -> UserRepository:
    return UserRepository(db)

def get_user_service(repo: UserRepository = Depends(get_repository)) -> UserService:
    return UserService(repo)

def get_current_user(
    token: Annotated[str, Depends(oauth2_scheme)],
    repo: Annotated[UserRepository, Depends(get_repository)],
) -> User:
    """A.9: Authenticate user via JWT."""
    try:
        payload = decode_token(token)
    except Exception:
        raise AuthenticationError("Invalid token")
    
    user = repo.get_by_id(payload.sub)
    if not user or not user.is_active:
        raise AuthenticationError("User not found or inactive")
    return user

def resolve_user(
    user_id: str,
    repo: UserRepository = Depends(get_repository)
) -> User:
    """Route Model Binding: Resolve user by ID or raise 404."""
    if user := repo.get_by_id(user_id):
        return user
    raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="User not found")