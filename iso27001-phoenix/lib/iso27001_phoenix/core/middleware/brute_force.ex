defmodule Iso27001Phoenix.Core.Middleware.BruteForce do
  @moduledoc """
  A.9: GenServer-backed brute-force lockout guard.

  After 5 consecutive authentication failures for the same identifier
  (email address), the account is locked for 15 minutes.

  State stored in Redis when available; falls back to ETS for resilience (A.17).
  """
  use GenServer

  @max_attempts 5
  @lockout_secs 900  # 15 minutes

  # ── Public API ────────────────────────────────────────────────────────────

  def start_link(opts) do
    GenServer.start_link(__MODULE__, opts, name: __MODULE__)
  end

  @doc "Returns {:ok, :allowed} or {:error, :locked}."
  def check(identifier) do
    GenServer.call(__MODULE__, {:check, identifier})
  end

  @doc "Records a failed attempt. Called after a bad password."
  def record_failure(identifier) do
    GenServer.cast(__MODULE__, {:record_failure, identifier})
  end

  @doc "Clears the failure counter on successful login."
  def clear(identifier) do
    GenServer.cast(__MODULE__, {:clear, identifier})
  end

  # ── GenServer ─────────────────────────────────────────────────────────────

  @impl true
  def init(_opts) do
    :ets.new(:brute_force_local, [:named_table, :public])
    redis = connect_redis()
    {:ok, %{redis: redis}}
  end

  @impl true
  def handle_call({:check, identifier}, _from, state) do
    result = do_check(state.redis, identifier)
    {:reply, result, state}
  end

  @impl true
  def handle_cast({:record_failure, identifier}, state) do
    do_record_failure(state.redis, identifier)
    {:noreply, state}
  end

  @impl true
  def handle_cast({:clear, identifier}, state) do
    do_clear(state.redis, identifier)
    {:noreply, state}
  end

  # ── Private ───────────────────────────────────────────────────────────────

  defp do_check(redis, identifier) do
    key = "brute_force:#{identifier}"

    result =
      case redis do
        nil -> local_get(key)
        r ->
          case Redix.command(r, ["GET", key]) do
            {:ok, nil}    -> :allowed
            {:ok, val}    -> if String.to_integer(val) >= @max_attempts, do: :locked, else: :allowed
            {:error, _}   -> local_get(key)
          end
      end

    case result do
      :locked  -> {:error, :locked}
      :allowed -> {:ok, :allowed}
    end
  end

  defp do_record_failure(redis, identifier) do
    key = "brute_force:#{identifier}"

    case redis do
      nil -> local_increment(key)
      r ->
        case Redix.pipeline(r, [["INCR", key], ["EXPIRE", key, @lockout_secs]]) do
          {:ok, _} -> :ok
          {:error, _} -> local_increment(key)
        end
    end
  end

  defp do_clear(redis, identifier) do
    key = "brute_force:#{identifier}"

    case redis do
      nil -> :ets.delete(:brute_force_local, key)
      r ->
        case Redix.command(r, ["DEL", key]) do
          {:ok, _} -> :ok
          {:error, _} -> :ets.delete(:brute_force_local, key)
        end
    end
  end

  defp local_get(key) do
    case :ets.lookup(:brute_force_local, key) do
      [{^key, count, expires_at}] ->
        if System.system_time(:second) < expires_at && count >= @max_attempts,
          do: :locked,
          else: :allowed
      _ -> :allowed
    end
  end

  defp local_increment(key) do
    now = System.system_time(:second)
    expires_at = now + @lockout_secs

    count =
      case :ets.lookup(:brute_force_local, key) do
        [{^key, c, _}] -> c + 1
        [] -> 1
      end

    :ets.insert(:brute_force_local, {key, count, expires_at})
  end

  defp connect_redis do
    url = Application.get_env(:iso27001_phoenix, :redis_url, "redis://localhost:6379/0")
    case Redix.start_link(url) do
      {:ok, pid} -> pid
      _ -> nil
    end
  end
end
