defmodule Iso27001Phoenix.Core.Middleware.AuthPipeline do
  @moduledoc """
  A.9: Guardian plug pipeline — validates Bearer token, halts with 401 if invalid.
  """
  use Guardian.Plug.Pipeline,
    otp_app: :iso27001_phoenix,
    module: Iso27001Phoenix.Guardian,
    error_handler: Iso27001Phoenix.Core.Middleware.AuthErrorHandler

  plug Guardian.Plug.VerifyHeader, scheme: "Bearer"
  plug Guardian.Plug.EnsureAuthenticated
  plug Guardian.Plug.LoadResource, ensure: true
end

defmodule Iso27001Phoenix.Core.Middleware.AuthErrorHandler do
  @moduledoc false
  import Plug.Conn
  import Phoenix.Controller

  @behaviour Guardian.Plug.ErrorHandler

  @impl true
  def auth_error(conn, {_type, _reason}, _opts) do
    conn
    |> put_status(401)
    |> json(%{error: %{code: "UNAUTHORIZED", message: "Invalid or missing token"}})
    |> halt()
  end
end
