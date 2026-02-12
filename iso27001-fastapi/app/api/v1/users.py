from typing import List
from fastapi import APIRouter, Depends, HTTPException, status, Query
from app.domain.users.schemas import CreateUserRequest, UserResponse, UpdateUserRequest
from app.domain.users.service import UserService
from app.api.deps import get_current_user, get_user_service, resolve_user
from app.domain.users.models import User

router = APIRouter()

@router.post("/", response_model=UserResponse, status_code=201)
def register_user(
    request: CreateUserRequest,
    service: UserService = Depends(get_user_service)
) -> UserResponse:
    """Register a new user."""
    return service.create_user(request)

@router.get("/", response_model=List[UserResponse])
def list_users(
    skip: int = Query(0, ge=0),
    limit: int = Query(20, ge=1, le=100),
    service: UserService = Depends(get_user_service),
    current_user: User = Depends(get_current_user),
) -> list[User]:
    """List users (Admin only)."""
    if current_user.role != "admin":
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Access denied")
    return service.list_users(skip, limit)

@router.get("/me", response_model=UserResponse)
def read_users_me(current_user: User = Depends(get_current_user)) -> User:
    """Get current authenticated user profile."""
    return current_user

@router.get("/{user_id}", response_model=UserResponse)
def get_user(
    user: User = Depends(resolve_user),
    current_user: User = Depends(get_current_user),
) -> User:
    """Get a specific user (Owner or Admin)."""
    if current_user.role != "admin" and current_user.id != user.id:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Access denied")
    return user

@router.patch("/{user_id}", response_model=UserResponse)
def update_user(
    request: UpdateUserRequest,
    user: User = Depends(resolve_user),
    service: UserService = Depends(get_user_service),
    current_user: User = Depends(get_current_user),
) -> User:
    """Update user profile (Owner or Admin)."""
    if current_user.role != "admin" and current_user.id != user.id:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Access denied")
    return service.update_user(user, request)

@router.delete("/{user_id}", status_code=204)
def delete_user(
    user: User = Depends(resolve_user),
    service: UserService = Depends(get_user_service),
    current_user: User = Depends(get_current_user),
) -> None:
    """Delete a user (Admin only)."""
    if current_user.role != "admin":
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Access denied")
    service.delete_user(user)