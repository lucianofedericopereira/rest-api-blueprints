import re
from pydantic import BaseModel, EmailStr, Field, field_validator
from datetime import datetime

_PASSWORD_PATTERN = re.compile(
    r"^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-_=+\[\]{};:'\",.<>?/\\|`~]).{12,}$"
)

class CreateUserRequest(BaseModel):
    email: EmailStr
    # A.9: minimum 12 chars, uppercase + lowercase + digit + special character required
    password: str = Field(min_length=12, description="A.9: Password complexity enforcement")
    full_name: str | None = None

    @field_validator("password")
    @classmethod
    def password_complexity(cls, v: str) -> str:
        if not _PASSWORD_PATTERN.match(v):
            raise ValueError(
                "Password must be at least 12 characters and contain uppercase, "
                "lowercase, a digit, and a special character."
            )
        return v

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