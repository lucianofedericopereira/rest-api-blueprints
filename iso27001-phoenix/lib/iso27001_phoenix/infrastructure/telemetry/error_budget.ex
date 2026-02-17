defmodule Iso27001Phoenix.Infrastructure.Telemetry.ErrorBudget do
  @moduledoc """
  A.17: Rolling error budget tracker — 99.9% SLA.

  Maintains atomic counters (via Agent) for total and failed requests.
  5xx responses deduct from the budget; 4xx do not (client errors, not service errors).

  budget_consumed % = (actual_error_rate / allowed_error_rate) * 100
  allowed_error_rate = 0.1% (= 1 - 99.9%)
  """
  use Agent

  @sla_pct 99.9
  @allowed_error_rate (100.0 - @sla_pct) / 100.0

  def start_link(_opts) do
    Agent.start_link(fn -> %{total: 0, failed: 0} end, name: __MODULE__)
  end

  @doc "Records an HTTP response status code."
  def record(status) when is_integer(status) do
    Agent.update(__MODULE__, fn state ->
      failed = if status >= 500, do: state.failed + 1, else: state.failed
      %{total: state.total + 1, failed: failed}
    end)
  end

  @doc "Returns a snapshot map with budget_remaining_pct."
  def snapshot do
    Agent.get(__MODULE__, fn %{total: total, failed: failed} ->
      error_rate = if total > 0, do: failed / total, else: 0.0
      consumed   = error_rate / @allowed_error_rate * 100.0
      remaining  = max(0.0, 100.0 - consumed)

      %{
        total_requests:       total,
        failed_requests:      failed,
        error_rate_pct:       Float.round(error_rate * 100.0, 4),
        budget_consumed_pct:  Float.round(consumed, 4),
        budget_remaining_pct: Float.round(remaining, 4),
        sla_pct:              @sla_pct
      }
    end)
  end

  @doc "Resets counters (useful in tests)."
  def reset do
    Agent.update(__MODULE__, fn _ -> %{total: 0, failed: 0} end)
  end
end
