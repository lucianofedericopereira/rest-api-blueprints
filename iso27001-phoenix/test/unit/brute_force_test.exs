defmodule Iso27001Phoenix.BruteForceTest do
  use ExUnit.Case, async: false

  alias Iso27001Phoenix.Core.Middleware.BruteForce

  @identifier "brute_test_#{System.unique_integer([:positive])}"

  setup do
    BruteForce.clear(@identifier)
    :ok
  end

  test "allows requests before threshold" do
    for _ <- 1..4, do: BruteForce.record_failure(@identifier)
    assert {:ok, :allowed} = BruteForce.check(@identifier)
  end

  test "locks after 5 failures" do
    for _ <- 1..5, do: BruteForce.record_failure(@identifier)
    assert {:error, :locked} = BruteForce.check(@identifier)
  end

  test "clear resets lockout" do
    for _ <- 1..5, do: BruteForce.record_failure(@identifier)
    assert {:error, :locked} = BruteForce.check(@identifier)
    BruteForce.clear(@identifier)
    assert {:ok, :allowed} = BruteForce.check(@identifier)
  end
end
