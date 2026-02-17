defmodule Iso27001Phoenix.Application do
  @moduledoc """
  OTP Application — supervises all processes (A.17).

  Supervision tree:
    Repo          — database connection pool
    RateLimiter   — GenServer (Redis + in-process fallback)
    BruteForce    — GenServer (Redis + in-process ETS fallback)
    ErrorBudget   — GenServer (atomic counters, 99.9% SLA)
    Endpoint      — Phoenix HTTP server
  """
  use Application

  @impl true
  def start(_type, _args) do
    children = [
      Iso27001Phoenix.Repo,
      {Iso27001Phoenix.Core.Middleware.RateLimiter, []},
      {Iso27001Phoenix.Core.Middleware.BruteForce, []},
      {Iso27001Phoenix.Infrastructure.Telemetry.ErrorBudget, []},
      Iso27001PhoenixWeb.Endpoint
    ]

    opts = [strategy: :one_for_one, name: Iso27001Phoenix.Supervisor]
    Supervisor.start_link(children, opts)
  end

  @impl true
  def config_change(changed, _new, removed) do
    Iso27001PhoenixWeb.Endpoint.config_change(changed, removed)
    :ok
  end
end
