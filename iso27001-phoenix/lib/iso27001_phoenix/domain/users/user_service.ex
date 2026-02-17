defmodule Iso27001Phoenix.Domain.Users.UserService do
  @moduledoc """
  A.9 / A.10 / A.12: User domain service.

  - Passwords hashed with bcrypt cost 12 (A.10)
  - Emits UserCreatedEvent with sha256(email) — never raw email (A.12)
  - Soft-delete preserves audit trail (A.12)
  """
  alias Iso27001Phoenix.Repo
  alias Iso27001Phoenix.Domain.Users.User

  @doc "Creates a new user; returns {:ok, user} or {:error, changeset}."
  def create_user(attrs) do
    role = Map.get(attrs, :role) || Map.get(attrs, "role") || "viewer"
    attrs = Map.put(attrs, :role, role)

    changeset = User.create_changeset(%User{}, attrs)

    case Repo.insert(changeset) do
      {:ok, user} ->
        publish_user_created(user)
        {:ok, user}
      {:error, cs} ->
        if email_conflict?(cs), do: {:error, :conflict}, else: {:error, cs}
    end
  end

  @doc "Lists users with skip/limit pagination."
  def list_users(skip \\ 0, limit \\ 20) do
    import Ecto.Query
    limit = min(limit, 100)

    users =
      from(u in User,
        where: is_nil(u.deleted_at),
        order_by: [asc: u.inserted_at],
        offset: ^skip,
        limit: ^limit
      )
      |> Repo.all()

    {:ok, users}
  end

  @doc "Partially updates a user."
  def update_user(%User{} = user, attrs) do
    changeset = User.update_changeset(user, attrs)

    case Repo.update(changeset) do
      {:ok, u} -> {:ok, u}
      {:error, cs} ->
        if email_conflict?(cs), do: {:error, :conflict}, else: {:error, cs}
    end
  end

  @doc "Soft-deletes a user (A.12: preserves audit trail)."
  def delete_user(%User{} = user) do
    import Ecto.Query
    now = DateTime.utc_now() |> DateTime.truncate(:second)

    {count, _} =
      from(u in User, where: u.id == ^user.id)
      |> Repo.update_all(set: [deleted_at: now])

    if count > 0, do: :ok, else: {:error, :not_found}
  end

  @doc "Verifies password with constant-time bcrypt comparison."
  def verify_password(%User{hashed_password: hash}, password) do
    Bcrypt.verify_pass(password, hash)
  end

  # ── Private ───────────────────────────────────────────────────────────────

  defp email_conflict?(cs) do
    cs.errors
    |> Keyword.get(:email, {nil, []})
    |> elem(1)
    |> Keyword.get(:constraint) == :unique
  end

  defp publish_user_created(user) do
    # A.12: emit event with sha256(email) — never raw email
    email_hash =
      :crypto.hash(:sha256, user.email)
      |> Base.encode16(case: :lower)

    event = %{
      user_id:    user.id,
      email_hash: email_hash,
      role:       user.role
    }

    Iso27001Phoenix.Infrastructure.Audit.EventBus.publish({:user_created, event})
  end
end
