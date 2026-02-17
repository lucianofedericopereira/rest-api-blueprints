defmodule Iso27001Phoenix.QualityScoreTest do
  use ExUnit.Case, async: true

  alias Iso27001Phoenix.Infrastructure.Telemetry.QualityScore

  test "perfect inputs yield composite score of 1.0" do
    score = QualityScore.calculate(
      auth_checks_passed:        100,
      auth_checks_total:         100,
      audit_events_recorded:     50,
      audit_events_expected:     50,
      availability:              1.0,
      logs_with_correlation_id:  200,
      total_logs:                200,
      p95_latency_ms:            0.0
    )

    assert Float.round(QualityScore.composite(score), 2) == 1.0
    assert QualityScore.passes_gate?(score)
  end

  test "zero security score fails production gate" do
    score = QualityScore.calculate(
      auth_checks_passed: 0,
      auth_checks_total:  100,
      availability: 1.0
    )

    refute QualityScore.passes_gate?(score)
  end

  test "slo_alert detects p95 latency breach" do
    alert = QualityScore.slo_alert(p95_latency_ms: 300.0, total_requests: 100)
    assert alert.p95_latency_breached
    assert alert.any_breach
  end

  test "slo_alert does not breach within thresholds" do
    alert = QualityScore.slo_alert(p95_latency_ms: 100.0, total_requests: 100)
    refute alert.p95_latency_breached
    refute alert.error_rate_breached
  end
end
