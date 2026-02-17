package com.iso27001.api.core.middleware;

import com.iso27001.api.infrastructure.telemetry.CloudWatchEmitter;
import com.iso27001.api.infrastructure.telemetry.ErrorBudgetTracker;
import jakarta.servlet.*;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.slf4j.MDC;
import org.springframework.core.annotation.Order;
import org.springframework.stereotype.Component;

import java.io.IOException;
import java.util.UUID;

/**
 * A.12 — Request telemetry: structured logging, correlation ID, X-Ray trace propagation,
 * error budget recording, CloudWatch metrics.
 */
@Component
@Order(1)
public class TelemetryFilter implements Filter {

    private static final Logger log = LoggerFactory.getLogger(TelemetryFilter.class);

    private final ErrorBudgetTracker errorBudget;
    private final CloudWatchEmitter cloudWatch;

    public TelemetryFilter(ErrorBudgetTracker errorBudget, CloudWatchEmitter cloudWatch) {
        this.errorBudget = errorBudget;
        this.cloudWatch = cloudWatch;
    }

    @Override
    public void doFilter(ServletRequest req, ServletResponse res, FilterChain chain)
        throws IOException, ServletException {

        HttpServletRequest  request  = (HttpServletRequest)  req;
        HttpServletResponse response = (HttpServletResponse) res;

        // A.12 — Correlation ID
        String correlationId = request.getHeader("X-Request-ID");
        if (correlationId == null || correlationId.isBlank()) {
            correlationId = UUID.randomUUID().toString();
        }
        response.setHeader("X-Request-ID", correlationId);
        MDC.put("request_id", correlationId);

        // A.12 — X-Ray trace propagation
        String traceId = request.getHeader("X-Amzn-Trace-Id");
        cloudWatch.extractTraceId(traceId).ifPresent(t -> response.setHeader("X-Amzn-Trace-Id", t));

        long startNs = System.nanoTime();

        log.info("{\"event\":\"request.started\",\"method\":\"{}\",\"path\":\"{}\",\"request_id\":\"{}\"}",
            request.getMethod(), request.getRequestURI(), correlationId);

        try {
            chain.doFilter(request, response);
        } finally {
            double durationMs = (System.nanoTime() - startNs) / 1_000_000.0;
            int status = response.getStatus();

            response.setHeader("X-Response-Time", String.format("%.2fms", durationMs));

            errorBudget.record(status);
            cloudWatch.emitRequest(request.getMethod(), request.getRequestURI(), status, durationMs);

            log.info("{\"event\":\"request.completed\",\"method\":\"{}\",\"path\":\"{}\",\"status\":{},\"duration_ms\":{},\"request_id\":\"{}\"}",
                request.getMethod(), request.getRequestURI(), status,
                String.format("%.2f", durationMs), correlationId);

            MDC.clear();
        }
    }
}
