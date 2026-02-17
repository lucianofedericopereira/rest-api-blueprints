package com.iso27001.api.core.config;

import com.iso27001.api.domain.users.UserRepository;
import com.iso27001.api.infrastructure.repositories.JpaUserRepository;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.scheduling.annotation.EnableAsync;
import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;
import org.springframework.security.crypto.password.PasswordEncoder;

/**
 * A.10 — BCrypt cost 12 for password hashing.
 */
@Configuration
@EnableAsync
public class AppConfig {

    @Bean
    public PasswordEncoder passwordEncoder() {
        return new BCryptPasswordEncoder(12);
    }

    @Bean
    public UserRepository userRepository(JpaUserRepository impl) {
        return impl;
    }
}
