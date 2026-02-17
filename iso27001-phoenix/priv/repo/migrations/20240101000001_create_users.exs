defmodule Iso27001Phoenix.Repo.Migrations.CreateUsers do
  use Ecto.Migration

  def change do
    create table(:users, primary_key: false) do
      add :id,              :uuid, primary_key: true, default: fragment("gen_random_uuid()")
      add :email,           :string, null: false
      add :hashed_password, :string, null: false
      add :full_name,       :string
      add :role,            :string, null: false, default: "viewer"
      add :is_active,       :boolean, null: false, default: true
      add :deleted_at,      :utc_datetime
      timestamps()
    end

    create unique_index(:users, [:email], where: "deleted_at IS NULL")
    create index(:users, [:role])
  end
end
