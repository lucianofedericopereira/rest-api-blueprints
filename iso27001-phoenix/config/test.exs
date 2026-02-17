import Config

config :iso27001_phoenix, Iso27001Phoenix.Repo,
  url:      "ecto://user:pass@localhost/iso27001_test",
  pool:     Ecto.Adapters.SQL.Sandbox,
  pool_size: 10

config :logger, level: :warning

config :iso27001_phoenix, Iso27001PhoenixWeb.Endpoint,
  server: false

config :iso27001_phoenix, Iso27001Phoenix.Guardian,
  secret_key: "test-secret-key-exactly-32-bytes!",
  ttl: {30, :minutes}

config :iso27001_phoenix, :encryption_key, "test-only-key-exactly-32-bytes!!"
