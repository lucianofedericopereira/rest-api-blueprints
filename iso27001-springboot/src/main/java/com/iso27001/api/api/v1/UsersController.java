package com.iso27001.api.api.v1;

import com.iso27001.api.domain.users.User;
import com.iso27001.api.domain.users.UserRole;
import com.iso27001.api.domain.users.UserService;
import jakarta.validation.Valid;
import jakarta.validation.constraints.*;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.server.ResponseStatusException;

import java.time.Instant;
import java.util.List;
import java.util.UUID;

/**
 * A.9 — User management endpoints with RBAC enforcement.
 */
@RestController
@RequestMapping("/api/v1/users")
public class UsersController {

    private final UserService userService;

    public UsersController(UserService userService) {
        this.userService = userService;
    }

    // ── DTOs ──────────────────────────────────────────────────────────────────

    record CreateUserRequest(
        @Email @NotBlank String email,
        @NotBlank @Size(min = 12, message = "Password must be at least 12 characters") String password,
        String fullName,
        String role
    ) {}

    record UpdateUserRequest(
        @Email String email,
        @Size(min = 12) String password,
        String fullName,
        String role
    ) {}

    record UserResponse(
        UUID id,
        String email,
        String fullName,
        String role,
        boolean isActive,
        Instant createdAt
    ) {}

    private UserResponse toResponse(User u) {
        return new UserResponse(u.getId(), u.getEmail(), u.getFullName(),
            u.getRole().name().toLowerCase(), u.isActive(), u.getCreatedAt());
    }

    // ── Endpoints ─────────────────────────────────────────────────────────────

    @PostMapping
    public ResponseEntity<UserResponse> create(@Valid @RequestBody CreateUserRequest req) {
        UserRole role = UserRole.VIEWER;
        if (req.role() != null) {
            try { role = UserRole.valueOf(req.role().toUpperCase()); } catch (IllegalArgumentException ignored) {}
        }
        try {
            User user = userService.create(req.email(), req.password(), req.fullName(), role);
            return ResponseEntity.status(HttpStatus.CREATED).body(toResponse(user));
        } catch (IllegalArgumentException e) {
            throw new ResponseStatusException(HttpStatus.CONFLICT, e.getMessage());
        }
    }

    @GetMapping
    @PreAuthorize("hasRole('ADMIN')")
    public ResponseEntity<List<UserResponse>> list(
        @RequestParam(defaultValue = "0") int skip,
        @RequestParam(defaultValue = "20") int limit
    ) {
        limit = Math.min(limit, 100);
        return ResponseEntity.ok(userService.findAll(skip, limit).stream().map(this::toResponse).toList());
    }

    @GetMapping("/me")
    public ResponseEntity<UserResponse> me(@AuthenticationPrincipal User currentUser) {
        if (currentUser == null) throw new ResponseStatusException(HttpStatus.UNAUTHORIZED, "Not authenticated");
        return ResponseEntity.ok(toResponse(currentUser));
    }

    @GetMapping("/{id}")
    public ResponseEntity<UserResponse> getOne(@PathVariable UUID id,
                                               @AuthenticationPrincipal User currentUser) {
        User user = userService.findById(id)
            .orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND, "User not found"));
        // A.9 — owner or admin
        if (!user.getId().equals(currentUser.getId()) && !currentUser.getRole().atLeast(UserRole.ADMIN)) {
            throw new ResponseStatusException(HttpStatus.FORBIDDEN, "Access denied");
        }
        return ResponseEntity.ok(toResponse(user));
    }

    @PatchMapping("/{id}")
    public ResponseEntity<UserResponse> update(@PathVariable UUID id,
                                               @Valid @RequestBody UpdateUserRequest req,
                                               @AuthenticationPrincipal User currentUser) {
        User user = userService.findById(id)
            .orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND, "User not found"));
        if (!user.getId().equals(currentUser.getId()) && !currentUser.getRole().atLeast(UserRole.ADMIN)) {
            throw new ResponseStatusException(HttpStatus.FORBIDDEN, "Access denied");
        }
        if (req.email() != null) user.setEmail(req.email());
        if (req.fullName() != null) user.setFullName(req.fullName());
        if (req.role() != null && currentUser.getRole().atLeast(UserRole.ADMIN)) {
            try { user.setRole(UserRole.valueOf(req.role().toUpperCase())); } catch (IllegalArgumentException ignored) {}
        }
        return ResponseEntity.ok(toResponse(userService.update(user)));
    }

    @DeleteMapping("/{id}")
    @PreAuthorize("hasRole('ADMIN')")
    public ResponseEntity<Void> delete(@PathVariable UUID id) {
        userService.findById(id)
            .orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND, "User not found"));
        userService.softDelete(id);
        return ResponseEntity.noContent().build();
    }
}
