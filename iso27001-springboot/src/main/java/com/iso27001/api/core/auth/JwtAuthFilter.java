package com.iso27001.api.core.auth;

import com.iso27001.api.domain.users.User;
import com.iso27001.api.domain.users.UserRole;
import com.iso27001.api.domain.users.UserService;
import io.jsonwebtoken.Claims;
import jakarta.servlet.FilterChain;
import jakarta.servlet.ServletException;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import org.springframework.lang.NonNull;
import org.springframework.security.authentication.UsernamePasswordAuthenticationToken;
import org.springframework.security.core.authority.SimpleGrantedAuthority;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.stereotype.Component;
import org.springframework.web.filter.OncePerRequestFilter;

import java.io.IOException;
import java.util.List;
import java.util.Optional;
import java.util.UUID;

/**
 * A.9 — JWT authentication filter. Validates Bearer token on every request.
 * Rejects refresh tokens used on non-refresh endpoints.
 */
@Component
public class JwtAuthFilter extends OncePerRequestFilter {

    private final JwtService jwtService;
    private final UserService userService;

    public JwtAuthFilter(JwtService jwtService, UserService userService) {
        this.jwtService = jwtService;
        this.userService = userService;
    }

    @Override
    protected void doFilterInternal(@NonNull HttpServletRequest request,
                                    @NonNull HttpServletResponse response,
                                    @NonNull FilterChain chain)
        throws ServletException, IOException {

        String header = request.getHeader("Authorization");
        if (header == null || !header.startsWith("Bearer ")) {
            chain.doFilter(request, response);
            return;
        }

        String token = header.substring(7);
        try {
            Claims claims = jwtService.parse(token);
            String type = claims.get("type", String.class);

            // Only access tokens allowed on non-refresh endpoints
            boolean isRefreshEndpoint = request.getRequestURI().endsWith("/auth/refresh");
            if ("refresh".equals(type) && !isRefreshEndpoint) {
                chain.doFilter(request, response);
                return;
            }

            String userId = claims.getSubject();
            Optional<User> userOpt = userService.findById(UUID.fromString(userId));
            if (userOpt.isEmpty() || !userOpt.get().isActive()) {
                chain.doFilter(request, response);
                return;
            }

            User user = userOpt.get();
            String role = user.getRole().name();
            var auth = new UsernamePasswordAuthenticationToken(
                user,
                null,
                List.of(new SimpleGrantedAuthority("ROLE_" + role))
            );
            SecurityContextHolder.getContext().setAuthentication(auth);

        } catch (Exception ignored) {
            // Invalid/expired token — leave SecurityContext empty; security config handles 401
        }

        chain.doFilter(request, response);
    }
}
