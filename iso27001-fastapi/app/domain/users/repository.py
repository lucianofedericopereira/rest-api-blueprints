from abc import ABC, abstractmethod
from typing import Optional, List
from sqlalchemy.orm import Session
from app.domain.users.models import User

class UserRepositoryInterface(ABC):
    @abstractmethod
    def save(self, user: User) -> User: ...
    @abstractmethod
    def get_by_email(self, email: str) -> Optional[User]: ...
    @abstractmethod
    def exists_by_email(self, email: str) -> bool: ...
    @abstractmethod
    def get_by_id(self, user_id: str) -> Optional[User]: ...
    @abstractmethod
    def get_all(self, skip: int, limit: int) -> List[User]: ...
    @abstractmethod
    def delete(self, user: User) -> None: ...

class UserRepository(UserRepositoryInterface):
    def __init__(self, db: Session):
        self.db = db

    def save(self, user: User) -> User:
        self.db.add(user)
        self.db.commit()
        self.db.refresh(user)
        return user

    def get_by_email(self, email: str) -> Optional[User]:
        return self.db.query(User).filter(User.email == email).first()

    def exists_by_email(self, email: str) -> bool:
        return self.db.query(User).filter(User.email == email).first() is not None

    def get_by_id(self, user_id: str) -> Optional[User]:
        return self.db.query(User).filter(User.id == user_id).first()

    def get_all(self, skip: int, limit: int) -> List[User]:
        return self.db.query(User).offset(skip).limit(limit).all()

    def delete(self, user: User) -> None:
        self.db.delete(user)
        self.db.commit()