defmodule Iso27001Phoenix.Core.Middleware.CorrelationId do
  @moduledoc """
  A.12: Propagates X-Request-ID through logs and responses.

  Uses the incoming header if present; generates a new UUID v4 otherwise.
  Stored in `conn.assigns.request_id` and in Logger metadata.
  """
  import Plug.Conn

  @behaviour Plug

  @impl true
  def init(opts), do: opts

  @impl true
  def call(conn, _opts) do
    request_id =
      case get_req_header(conn, "x-request-id") do
        [id | _] when byte_size(id) > 0 -> id
        _ -> Uniq.UUID.uuid4()
      end

    start_ms = System.monotonic_time(:millisecond)

    Logger.metadata(request_id: request_id)

    conn
    |> assign(:request_id, request_id)
    |> assign(:request_start_ms, start_ms)
    |> put_resp_header("x-request-id", request_id)
    |> register_before_send(fn conn ->
      elapsed = System.monotonic_time(:millisecond) - start_ms

      Iso27001Phoenix.Infrastructure.Telemetry.ErrorBudget.record(conn.status)
      Iso27001Phoenix.Core.Metrics.increment_request(conn.status)

      put_resp_header(conn, "x-response-time", "#{elapsed}ms")
    end)
  end
end
