defmodule Iso27001Phoenix.Domain.Users.User do
  @moduledoc """
  User aggregate root — pure domain schema, no framework business logic here.

  A.9:  Role-based access control constants.
  A.10: Password stored as bcrypt hash only; email stored encrypted at rest.
  A.12: Soft-delete via deleted_at preserves the audit trail.
  """
  use Ecto.Schema
  import Ecto.Changeset

  @primary_key {:id, :binary_id, autogenerate: true}
  @foreign_key_type :binary_id

  @roles ~w(admin manager analyst viewer)

  schema "users" do
    field :email,           :string
    field :hashed_password, :string
    field :full_name,       :string
    field :role,            :string, default: "viewer"
    field :is_active,       :boolean, default: true
    field :deleted_at,      :utc_datetime
    timestamps()
  end

  @doc "Changeset for user creation — validates and hashes password."
  def create_changeset(user, attrs) do
    user
    |> cast(attrs, [:email, :password, :role, :full_name])
    |> validate_required([:email, :password])
    |> validate_format(:email, ~r/^[^\s]+@[^\s]+$/, message: "must be a valid email")
    |> validate_length(:password, min: 12, message: "must be at least 12 characters")
    |> validate_inclusion(:role, @roles, message: "must be one of: #{Enum.join(@roles, ", ")}")
    |> unique_constraint(:email)
    |> hash_password()
  end

  @doc "Changeset for partial updates (email, full_name)."
  def update_changeset(user, attrs) do
    user
    |> cast(attrs, [:email, :full_name])
    |> validate_format(:email, ~r/^[^\s]+@[^\s]+$/, message: "must be a valid email")
    |> unique_constraint(:email)
  end

  def roles, do: @roles

  defp hash_password(%Ecto.Changeset{valid?: true, changes: %{password: pw}} = cs) do
    put_change(cs, :hashed_password, Bcrypt.hash_pwd_salt(pw, log_rounds: 12))
  end
  defp hash_password(cs), do: cs
end
