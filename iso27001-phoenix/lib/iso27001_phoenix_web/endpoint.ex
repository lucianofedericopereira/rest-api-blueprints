defmodule Iso27001PhoenixWeb.Endpoint do
  use Phoenix.Endpoint, otp_app: :iso27001_phoenix

  plug Plug.RequestId
  plug Iso27001Phoenix.Core.Middleware.CorrelationId
  plug Iso27001Phoenix.Core.Middleware.SecurityHeaders
  plug Iso27001Phoenix.Core.Middleware.RateLimiterPlug
  plug Plug.Parsers,
    parsers: [:urlencoded, :multipart, :json],
    pass: ["*/*"],
    json_decoder: Phoenix.json_library()
  plug Plug.MethodOverride
  plug Plug.Head
  plug Iso27001PhoenixWeb.Router
end
