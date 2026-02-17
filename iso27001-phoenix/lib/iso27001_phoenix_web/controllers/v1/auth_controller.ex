defmodule Iso27001PhoenixWeb.V1.AuthController do
  @moduledoc """
  A.9: Authentication endpoints — login, refresh, logout.
  """
  use Phoenix.Controller, formats: [:json]
  import Plug.Conn

  alias Iso27001Phoenix.Domain.Users.UserService
  alias Iso27001Phoenix.Guardian
  alias Iso27001Phoenix.Core.Middleware.BruteForce
  alias Iso27001Phoenix.Repo
  alias Iso27001Phoenix.Domain.Users.User
  import Ecto.Query

  @doc "POST /api/v1/auth/login"
  def login(conn, %{"email" => email, "password" => password}) do
    # A.9: Check brute-force lockout before touching DB
    case BruteForce.check(email) do
      {:error, :locked} ->
        conn |> put_status(429) |> json(error("LOCKED", "Account locked for 15 minutes"))

      {:ok, :allowed} ->
        user = from(u in User, where: u.email == ^email and is_nil(u.deleted_at)) |> Repo.one()

        if user && UserService.verify_password(user, password) do
          BruteForce.clear(email)

          case Guardian.issue_token_pair(user) do
            {:ok, tokens} -> json(conn, tokens)
            {:error, _}   -> conn |> put_status(500) |> json(error("INTERNAL_ERROR", "Token issuance failed"))
          end
        else
          BruteForce.record_failure(email)
          conn |> put_status(401) |> json(error("UNAUTHORIZED", "Invalid credentials"))
        end
    end
  end

  def login(conn, _), do: conn |> put_status(400) |> json(error("VALIDATION_ERROR", "email and password required"))

  @doc "POST /api/v1/auth/refresh"
  def refresh(conn, %{"refresh_token" => token}) do
    case Guardian.verify_refresh(token) do
      {:ok, user} ->
        case Guardian.issue_token_pair(user) do
          {:ok, tokens} -> json(conn, tokens)
          {:error, _}   -> conn |> put_status(500) |> json(error("INTERNAL_ERROR", "Token issuance failed"))
        end

      {:error, _} ->
        conn |> put_status(401) |> json(error("UNAUTHORIZED", "Invalid refresh token"))
    end
  end

  def refresh(conn, _), do: conn |> put_status(400) |> json(error("VALIDATION_ERROR", "refresh_token required"))

  @doc "POST /api/v1/auth/logout — stateless acknowledgement (A.9)"
  def logout(conn, _params) do
    send_resp(conn, 204, "")
  end

  defp error(code, message), do: %{error: %{code: code, message: message}}
end
