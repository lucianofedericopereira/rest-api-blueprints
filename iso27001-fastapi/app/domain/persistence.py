"""
SQLAlchemy declarative base — shared by all domain models.
Kept in domain so models don't cross into core/infrastructure.
"""
from sqlalchemy.orm import declarative_base

Base = declarative_base()
