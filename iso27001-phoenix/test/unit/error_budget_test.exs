defmodule Iso27001Phoenix.ErrorBudgetTest do
  use ExUnit.Case, async: false

  alias Iso27001Phoenix.Infrastructure.Telemetry.ErrorBudget

  setup do
    ErrorBudget.reset()
    :ok
  end

  test "initial state has full budget" do
    snap = ErrorBudget.snapshot()
    assert snap.budget_remaining_pct == 100.0
    assert snap.total_requests == 0
  end

  test "5xx responses consume budget" do
    ErrorBudget.record(500)
    ErrorBudget.record(200)
    snap = ErrorBudget.snapshot()
    assert snap.failed_requests == 1
    assert snap.total_requests == 2
    assert snap.budget_remaining_pct < 100.0
  end

  test "4xx responses do not consume budget" do
    ErrorBudget.record(400)
    ErrorBudget.record(404)
    snap = ErrorBudget.snapshot()
    assert snap.failed_requests == 0
    assert snap.budget_remaining_pct == 100.0
  end

  test "reset clears all counters" do
    ErrorBudget.record(500)
    ErrorBudget.reset()
    snap = ErrorBudget.snapshot()
    assert snap.total_requests == 0
    assert snap.failed_requests == 0
  end
end
