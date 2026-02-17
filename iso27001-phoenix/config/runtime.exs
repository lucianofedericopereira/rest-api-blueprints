import Config
import Dotenvy

# A.10: Load secrets from environment, never hardcoded
if config_env() in [:dev, :test] do
  source!([".env", System.get_env()])
end

source!([System.get_env()])

app_env = env!("APP_ENV", :string, "development")
port    = env!("PORT",    :integer, 8004)

config :iso27001_phoenix, :app,
  name:    env!("APP_NAME",    :string, "iso27001-phoenix"),
  version: env!("APP_VERSION", :string, "1.0.0"),
  env:     app_env

config :iso27001_phoenix, Iso27001PhoenixWeb.Endpoint,
  http: [port: port],
  url:  [host: "localhost", port: port],
  secret_key_base: env!("SECRET_KEY_BASE", :string, String.duplicate("a", 64)),
  server: true

config :iso27001_phoenix, Iso27001Phoenix.Repo,
  url:             env!("DATABASE_URL", :string, "ecto://user:pass@localhost/iso27001"),
  pool_size:       env!("POOL_SIZE", :integer, 10),
  ssl:             app_env == "production",
  show_sensitive_data_on_connection_error: app_env != "production"

# A.9: JWT secret
config :iso27001_phoenix, Iso27001Phoenix.Guardian,
  secret_key:              env!("JWT_SECRET", :string, "change-me-in-production"),
  ttl:                     {env!("JWT_ACCESS_TOKEN_EXPIRE_MINUTES", :integer, 30), :minutes},
  refresh_ttl:             {env!("JWT_REFRESH_TOKEN_EXPIRE_DAYS",   :integer, 7),  :days},
  allowed_algos:           ["HS256"],
  verify_issuer:           true

# A.10: AES-256-GCM encryption key — must be exactly 32 bytes
config :iso27001_phoenix, :encryption_key,
  env!("ENCRYPTION_KEY", :string, "dev-only-32-byte-key-change-me!!")

# Redis (optional — graceful GenServer fallback if unavailable)
config :iso27001_phoenix, :redis_url,
  env!("REDIS_URL", :string, "redis://localhost:6379/0")

# A.9: CORS
config :iso27001_phoenix, :cors_allowed_origins,
  env!("CORS_ALLOWED_ORIGINS", :string, "http://localhost:3000")
  |> String.split(",")
  |> Enum.map(&String.trim/1)
