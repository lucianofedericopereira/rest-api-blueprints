defmodule Iso27001Phoenix.Infrastructure.Telemetry.QualityScore do
  @moduledoc """
  A.17: Risk-weighted quality score calculator.

  Pillar           Weight   ISO 27001 Annex
  ─────────────────────────────────────────
  Security          40%     A.9, A.10
  Data Integrity    20%     A.12
  Reliability       15%     A.17
  Auditability      15%     A.12
  Performance        5%     A.17
  (Gap bonus)        5%     reserved

  Score 1.0 = perfect; gate threshold = 0.70.
  """

  @production_gate 0.70
  @weight_sum 0.95

  # SLO thresholds — A.17
  @slo_p95_latency_ms 200.0
  @slo_p99_latency_ms 500.0
  @slo_error_rate_pct 0.1
  @client_error_spike_pct 5.0

  defstruct [:security, :data_integrity, :reliability, :auditability, :performance]

  @doc "Computes the weighted composite score."
  def composite(%__MODULE__{} = s) do
    raw =
      s.security       * 0.40 +
      s.data_integrity * 0.20 +
      s.reliability    * 0.15 +
      s.auditability   * 0.15 +
      s.performance    * 0.05

    raw / @weight_sum
  end

  def passes_gate?(%__MODULE__{} = s), do: composite(s) >= @production_gate

  @doc "Builds a QualityScore from runtime signals."
  def calculate(opts) do
    auth_passed   = Keyword.get(opts, :auth_checks_passed, 1)
    auth_total    = Keyword.get(opts, :auth_checks_total, 1)
    audit_rec     = Keyword.get(opts, :audit_events_recorded, 1)
    audit_exp     = Keyword.get(opts, :audit_events_expected, 1)
    availability  = Keyword.get(opts, :availability, 1.0)
    logs_with_cid = Keyword.get(opts, :logs_with_correlation_id, 1)
    total_logs    = Keyword.get(opts, :total_logs, 1)
    p95_ms        = Keyword.get(opts, :p95_latency_ms, 0.0)

    %__MODULE__{
      security:       ratio(auth_passed, auth_total),
      data_integrity: ratio(audit_rec, audit_exp),
      reliability:    max(0.0, min(1.0, availability)),
      auditability:   ratio(logs_with_cid, total_logs),
      performance:    latency_score(p95_ms)
    }
  end

  @doc "Returns SLO breach flags."
  def slo_alert(opts \\ []) do
    failed  = Keyword.get(opts, :failed_requests, 0)
    clients = Keyword.get(opts, :client_errors, 0)
    total   = Keyword.get(opts, :total_requests, 0)
    p95_ms  = Keyword.get(opts, :p95_latency_ms, 0.0)
    p99_ms  = Keyword.get(opts, :p99_latency_ms, 0.0)

    error_rate_pct  = if total > 0, do: failed / total * 100.0, else: 0.0
    client_pct      = if total > 0, do: clients / total * 100.0, else: 0.0

    %{
      p95_latency_breached: p95_ms > @slo_p95_latency_ms,
      p99_latency_breached: p99_ms > @slo_p99_latency_ms,
      error_rate_breached:  error_rate_pct > @slo_error_rate_pct,
      client_error_spike:   client_pct > @client_error_spike_pct,
      any_breach: p95_ms > @slo_p95_latency_ms or p99_ms > @slo_p99_latency_ms or
                  error_rate_pct > @slo_error_rate_pct or client_pct > @client_error_spike_pct
    }
  end

  defp ratio(_, 0), do: 1.0
  defp ratio(n, d), do: max(0.0, min(1.0, n / d))

  defp latency_score(p95_ms) do
    target = @slo_p95_latency_ms
    max(0.0, 1.0 - p95_ms / target)
  end
end
