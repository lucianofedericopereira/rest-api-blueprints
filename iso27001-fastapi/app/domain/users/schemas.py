from pydantic import BaseModel, EmailStr, Field
from datetime import datetime

class CreateUserRequest(BaseModel):
    email: EmailStr
    password: str = Field(min_length=8, description="A.9: Minimum password length enforcement")
    full_name: str | None = None

class UpdateUserRequest(BaseModel):
    full_name: str | None = None
    email: EmailStr | None = None

class UserResponse(BaseModel):
    id: str
    email: EmailStr
    full_name: str | None
    role: str
    is_active: bool
    created_at: datetime

    class Config:
        from_attributes = True