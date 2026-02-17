defmodule Iso27001Phoenix.Repo.Migrations.CreateAuditLogs do
  use Ecto.Migration

  # A.12: append-only — no update or delete routes in the application
  def change do
    create table(:audit_logs, primary_key: false) do
      add :id,             :uuid, primary_key: true, default: fragment("gen_random_uuid()")
      add :action,         :string, null: false
      add :performed_by,   :string
      add :resource_type,  :string, null: false
      add :resource_id,    :string
      add :changes,        :map, default: %{}
      add :ip_address,     :string
      add :correlation_id, :string
      timestamps(updated_at: false)
    end

    create index(:audit_logs, [:resource_type, :resource_id])
    create index(:audit_logs, [:performed_by])
    create index(:audit_logs, [:inserted_at])
  end
end
