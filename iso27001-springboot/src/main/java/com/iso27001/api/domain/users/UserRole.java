package com.iso27001.api.domain.users;

/**
 * A.9 — RBAC roles with strict hierarchy.
 * admin > manager > analyst > viewer
 */
public enum UserRole {
    VIEWER(0),
    ANALYST(1),
    MANAGER(2),
    ADMIN(3);

    private final int level;

    UserRole(int level) {
        this.level = level;
    }

    public boolean atLeast(UserRole required) {
        return this.level >= required.level;
    }
}
