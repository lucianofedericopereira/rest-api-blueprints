import hashlib
from app.domain.users.repository import UserRepositoryInterface
from app.domain.users.models import User
from app.domain.users.schemas import CreateUserRequest, UpdateUserRequest
from app.domain.users.events import UserCreated
from app.config.security import hash_password
from app.domain.exceptions import ConflictError
from app.domain.events import EventBus, event_bus

class UserService:
    def __init__(self, repo: UserRepositoryInterface, bus: EventBus = event_bus):
        self.repo = repo
        self.bus = bus

    def create_user(self, request: CreateUserRequest) -> User:
        if self.repo.exists_by_email(request.email):
            raise ConflictError("Email already exists")
        
        hashed_pw = hash_password(request.password)
        user = User(
            email=request.email,
            hashed_password=hashed_pw,
            full_name=request.full_name,
            role="viewer"
        )
        saved_user = self.repo.save(user)
        
        # A.12: Emit event for audit trail (never log raw email)
        email_hash = hashlib.sha256(str(saved_user.email).encode()).hexdigest()
        event = UserCreated(user_id=str(saved_user.id), email_hash=email_hash, role=str(saved_user.role))
        self.bus.publish(event)
        
        return saved_user

    def list_users(self, skip: int, limit: int) -> list[User]:
        return self.repo.get_all(skip, limit)

    def update_user(self, user: User, request: UpdateUserRequest) -> User:
        if request.email and request.email != user.email:
            if self.repo.exists_by_email(request.email):
                raise ConflictError("Email already exists")
            user.email = request.email  # type: ignore[assignment]

        if request.full_name:
            user.full_name = request.full_name  # type: ignore[assignment]
            
        return self.repo.save(user)

    def delete_user(self, user: User) -> None:
        self.repo.delete(user)