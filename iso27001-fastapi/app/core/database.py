from typing import Generator
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker, Session
from app.config.settings import settings
from app.domain.persistence import Base  # re-exported for infrastructure consumers

# A.12: Database connection configuration
engine = create_engine(
    settings.DATABASE_URL,
    connect_args={"check_same_thread": False} if "sqlite" in settings.DATABASE_URL else {}
)

SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

__all__ = ["Base", "engine", "SessionLocal", "get_db"]

def get_db() -> Generator[Session, None, None]:
    """Dependency for database session management."""
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()