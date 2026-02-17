defmodule Iso27001Phoenix.Core.Middleware.RateLimiter do
  @moduledoc """
  A.9 / A.17: GenServer-based tiered sliding-window rate limiter.

  Tiers:
    auth   — 10  req/min per IP  (login endpoints)
    write  — 30  req/min per IP  (POST/PUT/PATCH/DELETE)
    global — 100 req/min per IP  (everything else)

  Redis-backed via Redix with Lua script for atomic ZREMRANGEBYSCORE+ZCARD+ZADD.
  Falls back to an in-process ETS table when Redis is unavailable (A.17: continuity).

  Use `Iso27001Phoenix.Core.Middleware.RateLimiterPlug` as a Plug in your pipeline.
  """
  use GenServer

  @limits %{auth: 10, write: 30, global: 100}
  @window 60

  @lua_script """
  local key = KEYS[1]
  local limit = tonumber(ARGV[1])
  local now = tonumber(ARGV[2])
  local window = tonumber(ARGV[3])
  local clear_before = now - window

  redis.call('ZREMRANGEBYSCORE', key, 0, clear_before)
  local count = redis.call('ZCARD', key)

  if count < limit then
      redis.call('ZADD', key, now, tostring(now) .. ':' .. math.random(1000000))
      redis.call('EXPIRE', key, window)
      return 0
  else
      return 1
  end
  """

  # ── Public API ────────────────────────────────────────────────────────────

  def start_link(opts) do
    GenServer.start_link(__MODULE__, opts, name: __MODULE__)
  end

  @doc "Returns {allowed?, limit} for the given key and tier."
  def check(key, tier) do
    GenServer.call(__MODULE__, {:check, key, tier})
  end

  def limits, do: @limits

  # ── GenServer callbacks ───────────────────────────────────────────────────

  @impl true
  def init(_opts) do
    :ets.new(:rate_limit_local, [:named_table, :public, read_concurrency: true])
    redis = connect_redis()
    {:ok, %{redis: redis}}
  end

  @impl true
  def handle_call({:check, key, tier}, _from, state) do
    limit = Map.fetch!(@limits, tier)
    now   = System.system_time(:second)

    {allowed, new_state} =
      case check_redis(state.redis, key, limit, now) do
        {:ok, result}       -> {result, state}
        {:error, :no_redis} ->
          redis = connect_redis()
          {local_check(key, limit, now), %{state | redis: redis}}
      end

    {:reply, {allowed, limit}, new_state}
  end

  # ── Private ───────────────────────────────────────────────────────────────

  defp check_redis(nil, key, limit, now), do: local_check_result(key, limit, now)
  defp check_redis(redis, key, limit, now) do
    case Redix.command(redis, ["EVAL", @lua_script, "1", key, limit, now, @window]) do
      {:ok, 0} -> {:ok, false}
      {:ok, 1} -> {:ok, true}
      {:error, _} -> {:error, :no_redis}
    end
  end

  defp local_check(key, limit, now) do
    case local_check_result(key, limit, now) do
      {:ok, result} -> result
      _ -> false
    end
  end

  defp local_check_result(key, limit, now) do
    cutoff = now - @window
    entries =
      case :ets.lookup(:rate_limit_local, key) do
        [{^key, list}] -> Enum.filter(list, &(&1 > cutoff))
        [] -> []
      end

    if length(entries) >= limit do
      :ets.insert(:rate_limit_local, {key, entries})
      {:ok, true}
    else
      :ets.insert(:rate_limit_local, {key, [now | entries]})
      {:ok, false}
    end
  end

  defp connect_redis do
    url = Application.get_env(:iso27001_phoenix, :redis_url, "redis://localhost:6379/0")
    case Redix.start_link(url) do
      {:ok, pid} -> pid
      _ -> nil
    end
  end
end

defmodule Iso27001Phoenix.Core.Middleware.RateLimiterPlug do
  @moduledoc """
  Plug wrapper around `RateLimiter` GenServer.
  Add this to your Phoenix pipeline instead of the GenServer module directly.
  """
  import Plug.Conn
  alias Iso27001Phoenix.Core.Middleware.RateLimiter

  def init(opts), do: opts

  def call(conn, _opts) do
    tier  = tier(conn)
    ip    = conn.remote_ip |> :inet.ntoa() |> to_string()
    key   = "rate_limit:#{tier}:#{ip}"

    case RateLimiter.check(key, tier) do
      {false, limit} ->
        conn
        |> put_resp_header("x-ratelimit-limit", to_string(limit))
        |> put_resp_header("x-ratelimit-remaining", "0")

      {true, limit} ->
        conn
        |> put_resp_header("x-ratelimit-limit", to_string(limit))
        |> put_resp_header("x-ratelimit-remaining", "0")
        |> put_status(429)
        |> Phoenix.Controller.json(%{error: %{code: "RATE_LIMIT", message: "Rate limit exceeded (#{tier}: #{limit}/min)"}})
        |> halt()
    end
  end

  defp tier(conn) do
    path   = conn.request_path
    method = conn.method

    cond do
      String.contains?(path, "/auth")        -> :auth
      method in ~w(POST PUT PATCH DELETE)     -> :write
      true                                    -> :global
    end
  end
end
