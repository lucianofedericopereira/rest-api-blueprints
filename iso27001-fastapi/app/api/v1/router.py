"""
API v1 aggregator router.
All v1 sub-routers are registered here and included in main.py with the /api/v1 prefix.
"""
from fastapi import APIRouter

from app.api.v1 import auth, health, users

router = APIRouter()

router.include_router(auth.router, prefix="/auth", tags=["auth"])
router.include_router(users.router, prefix="/users", tags=["users"])
router.include_router(health.router, prefix="/health", tags=["health"])
