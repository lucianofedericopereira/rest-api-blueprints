defmodule Iso27001Phoenix.Repo do
  use Ecto.Repo,
    otp_app: :iso27001_phoenix,
    adapter: Ecto.Adapters.Postgres
end
