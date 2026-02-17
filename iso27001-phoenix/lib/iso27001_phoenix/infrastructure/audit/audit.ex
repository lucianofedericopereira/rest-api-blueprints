defmodule Iso27001Phoenix.Infrastructure.Audit.AuditLog do
  @moduledoc """
  A.12: Immutable append-only audit log entry.

  Records are never updated or deleted — the table has no update/delete routes.
  """
  use Ecto.Schema
  import Ecto.Changeset

  @primary_key {:id, :binary_id, autogenerate: true}
  @foreign_key_type :binary_id

  schema "audit_logs" do
    field :action,         :string
    field :performed_by,   :string
    field :resource_type,  :string
    field :resource_id,    :string
    field :changes,        :map, default: %{}
    field :ip_address,     :string
    field :correlation_id, :string
    timestamps(updated_at: false)
  end

  def changeset(log, attrs) do
    log
    |> cast(attrs, [:action, :performed_by, :resource_type, :resource_id, :changes, :ip_address, :correlation_id])
    |> validate_required([:action, :resource_type])
  end
end

defmodule Iso27001Phoenix.Infrastructure.Audit.AuditService do
  @moduledoc """
  A.12: Best-effort audit record persistence.

  Failures are logged but never bubble up — audit must not break the happy path.
  """
  alias Iso27001Phoenix.Repo
  alias Iso27001Phoenix.Infrastructure.Audit.AuditLog
  require Logger

  def record(attrs) do
    %AuditLog{}
    |> AuditLog.changeset(attrs)
    |> Repo.insert()
    |> case do
      {:ok, log} -> {:ok, log}
      {:error, cs} ->
        Logger.warning("audit_record_failed changeset=#{inspect(cs.errors)}")
        {:error, :audit_failed}
    end
  rescue
    e ->
      Logger.warning("audit_record_exception #{inspect(e)}")
      {:error, :audit_failed}
  end

  def on_user_created({:user_created, %{user_id: uid, email_hash: eh, role: role}}) do
    record(%{
      action:        "user.created",
      performed_by:  uid,
      resource_type: "user",
      resource_id:   uid,
      changes:       %{email_hash: eh, role: role}
    })
  end
  def on_user_created(_), do: :ok
end
