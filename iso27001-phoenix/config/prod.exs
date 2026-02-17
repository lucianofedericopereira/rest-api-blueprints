import Config

config :logger, level: :info

config :iso27001_phoenix, Iso27001PhoenixWeb.Endpoint,
  cache_static_manifest: "priv/static/cache_manifest.json",
  server: true
