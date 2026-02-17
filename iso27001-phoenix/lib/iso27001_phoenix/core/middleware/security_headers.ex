defmodule Iso27001Phoenix.Core.Middleware.SecurityHeaders do
  @moduledoc """
  A.10: Plug that adds all required security headers and removes
  server-identifying headers on every response.
  """
  import Plug.Conn

  @behaviour Plug

  @impl true
  def init(opts), do: opts

  @impl true
  def call(conn, _opts) do
    conn
    |> delete_resp_header("server")
    |> delete_resp_header("x-powered-by")
    |> put_resp_header("strict-transport-security", "max-age=31536000; includeSubDomains; preload")
    |> put_resp_header("x-frame-options", "DENY")
    |> put_resp_header("x-content-type-options", "nosniff")
    |> put_resp_header("content-security-policy", "default-src 'none'")
    |> put_resp_header("x-xss-protection", "1; mode=block")
    |> put_resp_header("referrer-policy", "strict-origin-when-cross-origin")
    |> put_resp_header("permissions-policy", "geolocation=(), microphone=(), camera=()")
    |> put_resp_header("cache-control", "no-store")
  end
end
