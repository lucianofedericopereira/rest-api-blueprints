import Config

config :iso27001_phoenix, Iso27001Phoenix.Repo,
  adapter: Ecto.Adapters.Postgres

config :iso27001_phoenix, Iso27001Phoenix.Guardian,
  issuer: "iso27001_phoenix",
  ttl: {30, :minutes}

config :logger, :console,
  format: "$time $metadata[$level] $message\n",
  metadata: [:request_id]

config :phoenix, :json_library, Jason

import_config "#{config_env()}.exs"
