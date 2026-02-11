<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * A.17: Prometheus-compatible metrics endpoint.
 *
 * Exposes counters and histograms in the OpenMetrics text format when the
 * promphp/prometheus_client_php package is installed. Degrades gracefully
 * to a plain-text stub when the SDK is absent so the application always
 * starts in dev/test environments without any extra dependencies.
 *
 * Installation (optional):
 *   composer require promphp/prometheus_client_php
 *
 * Scrape config (prometheus.yml):
 *   - job_name: iso27001-laravel
 *     static_configs:
 *       - targets: ['api:8080']
 *     metrics_path: /metrics
 *     bearer_token: <admin-jwt>
 */
final class MetricsController extends Controller
{
    public function __invoke(): Response
    {
        // If prometheus_client_php is installed, render its registry
        if (class_exists(\Prometheus\CollectorRegistry::class)) {
            /** @phpstan-ignore-next-line */
            $renderer = new \Prometheus\RenderTextFormat();
            /** @phpstan-ignore-next-line */
            $registry = \Prometheus\CollectorRegistry::getDefault();
            return response(
                $renderer->render($registry->getMetricFamilySamples()),
                Response::HTTP_OK,
                ['Content-Type' => \Prometheus\RenderTextFormat::MIME_TYPE],
            );
        }

        // Graceful stub — tells scrapers the endpoint exists but SDK is absent
        return response(
            "# Prometheus metrics not available: install promphp/prometheus_client_php\n"
            . "# TYPE iso27001_metrics_available gauge\n"
            . "iso27001_metrics_available 0\n",
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain; version=0.0.4; charset=utf-8'],
        );
    }
}
