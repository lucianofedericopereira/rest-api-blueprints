defmodule Iso27001Phoenix.Infrastructure.Audit.EventBus do
  @moduledoc """
  A.12: Simple in-process pub/sub event bus using Registry.

  Subscribers register with `subscribe/1`. Events are dispatched
  synchronously to all subscribers in the calling process.
  """

  @registry Iso27001Phoenix.EventRegistry

  def child_spec(_opts) do
    Registry.child_spec(keys: :duplicate, name: @registry)
  end

  def subscribe(event_type) do
    Registry.register(@registry, event_type, [])
  end

  def publish({event_type, _payload} = event) do
    Registry.dispatch(@registry, event_type, fn entries ->
      for {pid, _} <- entries, do: send(pid, {:event, event})
    end)

    # Also call audit listener directly for audit-log persistence
    Iso27001Phoenix.Infrastructure.Audit.AuditService.on_user_created(event)
  end
end
