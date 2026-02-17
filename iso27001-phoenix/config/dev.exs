import Config

config :logger, level: :debug

config :iso27001_phoenix, Iso27001PhoenixWeb.Endpoint,
  debug_errors: true,
  check_origin: false
