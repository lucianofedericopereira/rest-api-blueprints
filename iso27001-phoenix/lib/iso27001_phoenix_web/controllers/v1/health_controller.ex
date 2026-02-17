defmodule Iso27001PhoenixWeb.V1.HealthController do
  @moduledoc """
  A.17: Liveness, readiness, and detailed health endpoints.
  """
  use Phoenix.Controller, formats: [:json]
  import Plug.Conn

  alias Iso27001Phoenix.Repo
  alias Iso27001Phoenix.Guardian
  alias Iso27001Phoenix.Infrastructure.Telemetry.{ErrorBudget, QualityScore}

  @start_time System.monotonic_time(:second)

  @doc "GET /api/v1/health/live — no dependency checks (A.17)"
  def live(conn, _params) do
    json(conn, %{status: "ok"})
  end

  @doc "GET /api/v1/health/ready — DB ping required (A.17)"
  def ready(conn, _params) do
    db_status =
      try do
        Repo.query!("SELECT 1")
        "ok"
      rescue
        _ -> "error"
      end

    if db_status == "ok" do
      json(conn, %{status: "ok", checks: %{database: "ok"}})
    else
      conn
      |> put_status(503)
      |> json(%{status: "degraded", checks: %{database: "error"}})
    end
  end

  @doc "GET /api/v1/health/detailed — admin only (A.17)"
  def detailed(conn, _params) do
    with :ok <- require_admin(conn) do
      budget  = ErrorBudget.snapshot()
      score   = QualityScore.calculate(availability: budget.budget_remaining_pct / 100.0)
      uptime  = System.monotonic_time(:second) - @start_time

      json(conn, %{
        status:                      "ok",
        uptime_seconds:              uptime,
        error_budget_remaining_pct:  budget.budget_remaining_pct,
        error_budget_consumed_pct:   budget.budget_consumed_pct,
        quality_score:               Float.round(QualityScore.composite(score), 4),
        quality_passes_gate:         QualityScore.passes_gate?(score),
        slo_alerts:                  QualityScore.slo_alert(),
        components: %{
          database: db_check()
        }
      })
    end
  end

  defp require_admin(conn) do
    user = Guardian.Plug.current_resource(conn)

    if user && user.role == "admin" do
      :ok
    else
      conn
      |> put_status(403)
      |> json(%{error: %{code: "FORBIDDEN", message: "Admin role required"}})
      |> halt()
    end
  end

  defp db_check do
    try do
      Repo.query!("SELECT 1")
      "ok"
    rescue
      _ -> "error"
    end
  end
end
