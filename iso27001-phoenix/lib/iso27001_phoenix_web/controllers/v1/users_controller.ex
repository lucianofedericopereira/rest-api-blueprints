defmodule Iso27001PhoenixWeb.V1.UsersController do
  @moduledoc """
  A.9: User CRUD endpoints with RBAC ownership checks.
  """
  use Phoenix.Controller, formats: [:json]
  import Plug.Conn

  alias Iso27001Phoenix.Domain.Users.{User, UserService}
  alias Iso27001Phoenix.Guardian
  alias Iso27001Phoenix.Repo

  # ── Create ────────────────────────────────────────────────────────────────

  def create(conn, params) do
    attrs = Map.take(params, ~w(email password role full_name))

    case UserService.create_user(attrs) do
      {:ok, user} ->
        conn |> put_status(201) |> json(user_response(user))

      {:error, :conflict} ->
        conn |> put_status(409) |> json(error("CONFLICT", "Email already exists"))

      {:error, cs} ->
        msg = format_changeset_errors(cs)
        conn |> put_status(400) |> json(error("VALIDATION_ERROR", msg))
    end
  end

  # ── List (admin only) ─────────────────────────────────────────────────────

  def index(conn, params) do
    with :ok <- require_role(conn, "admin") do
      skip  = parse_int(params["skip"],  0)
      limit = parse_int(params["limit"], 20)

      {:ok, users} = UserService.list_users(skip, limit)
      json(conn, Enum.map(users, &user_response/1))
    end
  end

  # ── Me ────────────────────────────────────────────────────────────────────

  def me(conn, _params) do
    user = Guardian.Plug.current_resource(conn)
    json(conn, user_response(user))
  end

  # ── Get ───────────────────────────────────────────────────────────────────

  def show(conn, %{"id" => id}) do
    with :ok <- require_owner_or_admin(conn, id),
         %User{} = user <- Repo.get(User, id) do
      json(conn, user_response(user))
    else
      nil   -> conn |> put_status(404) |> json(error("NOT_FOUND", "User not found"))
      error -> error
    end
  end

  # ── Update ────────────────────────────────────────────────────────────────

  def update(conn, %{"id" => id} = params) do
    with :ok <- require_owner_or_admin(conn, id),
         %User{} = user <- Repo.get(User, id) do
      attrs = Map.take(params, ~w(email full_name))

      case UserService.update_user(user, attrs) do
        {:ok, updated} -> json(conn, user_response(updated))
        {:error, :conflict} -> conn |> put_status(409) |> json(error("CONFLICT", "Email already exists"))
        {:error, cs}        -> conn |> put_status(400) |> json(error("VALIDATION_ERROR", format_changeset_errors(cs)))
      end
    else
      nil   -> conn |> put_status(404) |> json(error("NOT_FOUND", "User not found"))
      error -> error
    end
  end

  # ── Delete (admin only, soft) ─────────────────────────────────────────────

  def delete(conn, %{"id" => id}) do
    with :ok <- require_role(conn, "admin"),
         %User{} = user <- Repo.get(User, id) do
      case UserService.delete_user(user) do
        :ok               -> send_resp(conn, 204, "")
        {:error, :not_found} -> conn |> put_status(404) |> json(error("NOT_FOUND", "User not found"))
      end
    else
      nil   -> conn |> put_status(404) |> json(error("NOT_FOUND", "User not found"))
      error -> error
    end
  end

  # ── Private ───────────────────────────────────────────────────────────────

  defp user_response(%User{} = u) do
    %{
      id:         u.id,
      email:      u.email,
      full_name:  u.full_name,
      role:       u.role,
      is_active:  u.is_active,
      created_at: u.inserted_at
    }
  end

  defp require_role(conn, required_role) do
    current = Guardian.Plug.current_resource(conn)

    if role_level(current.role) >= role_level(required_role) do
      :ok
    else
      conn
      |> put_status(403)
      |> json(error("FORBIDDEN", "Access denied"))
      |> halt()
    end
  end

  # A.9: owner or admin can access their own resource
  defp require_owner_or_admin(conn, resource_user_id) do
    current = Guardian.Plug.current_resource(conn)

    if to_string(current.id) == resource_user_id or current.role == "admin" do
      :ok
    else
      conn
      |> put_status(403)
      |> json(error("FORBIDDEN", "Access denied"))
      |> halt()
    end
  end

  defp role_level("admin"),   do: 4
  defp role_level("manager"), do: 3
  defp role_level("analyst"), do: 2
  defp role_level("viewer"),  do: 1
  defp role_level(_),         do: 0

  defp parse_int(nil, default), do: default
  defp parse_int(val, _) when is_integer(val), do: val
  defp parse_int(val, default) do
    case Integer.parse(to_string(val)) do
      {n, _} -> n
      :error -> default
    end
  end

  defp format_changeset_errors(cs) do
    Ecto.Changeset.traverse_errors(cs, fn {msg, opts} ->
      Enum.reduce(opts, msg, fn {k, v}, acc ->
        String.replace(acc, "%{#{k}}", to_string(v))
      end)
    end)
    |> Enum.map(fn {field, msgs} -> "#{field}: #{Enum.join(msgs, ", ")}" end)
    |> Enum.join("; ")
  end

  defp error(code, message), do: %{error: %{code: code, message: message}}
end
