-- A.12 — Initial schema: users + audit_logs

CREATE TABLE IF NOT EXISTS users (
    id            UUID        PRIMARY KEY,
    email         VARCHAR(320) NOT NULL UNIQUE,
    hashed_password VARCHAR(72) NOT NULL,
    full_name     VARCHAR(255),
    role          VARCHAR(20)  NOT NULL DEFAULT 'VIEWER',
    is_active     BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at    TIMESTAMPTZ
);

CREATE INDEX idx_users_email      ON users(email) WHERE deleted_at IS NULL;
CREATE INDEX idx_users_deleted_at ON users(deleted_at);

-- A.12 — Immutable audit log: no UPDATE/DELETE granted on this table in production
CREATE TABLE IF NOT EXISTS audit_logs (
    id             UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    action         VARCHAR(100) NOT NULL,
    performed_by   VARCHAR(255) NOT NULL,
    resource_type  VARCHAR(100) NOT NULL,
    resource_id    VARCHAR(255) NOT NULL,
    changes        JSONB,
    ip_address     VARCHAR(45),
    correlation_id VARCHAR(36),
    created_at     TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_audit_resource ON audit_logs(resource_type, resource_id);
CREATE INDEX idx_audit_created  ON audit_logs(created_at);
