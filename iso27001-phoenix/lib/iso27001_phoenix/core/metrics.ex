defmodule Iso27001Phoenix.Core.Metrics do
  @moduledoc """
  A.17: Prometheus counters and histograms.

  Registered once at startup; safe to call from any process.
  """
  use Prometheus.Metric

  # SLO constants — A.17
  @slo_p95_latency_ms 200
  @slo_p99_latency_ms 500
  @slo_error_rate_pct 0.1

  def setup do
    Counter.declare(
      name: :http_requests_total,
      help: "Total HTTP requests",
      labels: [:status_class]
    )

    Histogram.declare(
      name: :http_request_duration_ms,
      help: "HTTP request duration in milliseconds",
      labels: [:status_class],
      buckets: [5, 10, 25, 50, 100, 200, 500, 1000]
    )

    Counter.declare(
      name: :auth_errors_total,
      help: "Total authentication errors",
      labels: [:reason]
    )
  end

  def increment_request(status) do
    class = status_class(status)
    Counter.inc(name: :http_requests_total, labels: [class])
  end

  def observe_duration(status, duration_ms) do
    class = status_class(status)
    Histogram.observe([name: :http_request_duration_ms, labels: [class]], duration_ms)
  end

  def slo_p95_latency_ms, do: @slo_p95_latency_ms
  def slo_p99_latency_ms, do: @slo_p99_latency_ms
  def slo_error_rate_pct, do: @slo_error_rate_pct

  defp status_class(s) when s >= 500, do: "5xx"
  defp status_class(s) when s >= 400, do: "4xx"
  defp status_class(s) when s >= 300, do: "3xx"
  defp status_class(_), do: "2xx"
end
