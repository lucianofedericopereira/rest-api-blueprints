defmodule Iso27001Phoenix.MixProject do
  use Mix.Project

  def project do
    [
      app: :iso27001_phoenix,
      version: "1.0.0",
      elixir: "~> 1.16",
      elixirc_paths: elixirc_paths(Mix.env()),
      start_permanent: Mix.env() == :prod,
      aliases: aliases(),
      deps: deps()
    ]
  end

  def application do
    [
      mod: {Iso27001Phoenix.Application, []},
      extra_applications: [:logger, :runtime_tools, :crypto]
    ]
  end

  defp elixirc_paths(:test), do: ["lib", "test/support"]
  defp elixirc_paths(_), do: ["lib"]

  defp deps do
    [
      # Phoenix
      {:phoenix, "~> 1.7"},
      {:phoenix_ecto, "~> 4.5"},
      {:plug_cowboy, "~> 2.7"},

      # Database
      {:ecto_sql, "~> 3.11"},
      {:postgrex, "~> 0.17"},

      # Redis
      {:redix, "~> 1.4"},

      # Auth — A.9
      {:guardian, "~> 2.3"},
      {:bcrypt_elixir, "~> 3.1"},

      # Crypto — A.10
      # stdlib :crypto used directly for AES-256-GCM

      # Validation
      {:jason, "~> 1.4"},

      # Prometheus — A.17
      {:prometheus_ex, "~> 3.0"},
      {:prometheus_plugs, "~> 1.1"},

      # Config
      {:dotenvy, "~> 0.8"},

      # UUID
      {:uniq, "~> 0.6"},

      # Test
      {:mox, "~> 1.1", only: :test},
      {:ex_machina, "~> 2.8", only: :test}
    ]
  end

  defp aliases do
    [
      setup: ["deps.get", "ecto.create", "ecto.migrate"],
      "ecto.setup": ["ecto.create", "ecto.migrate"],
      "ecto.reset": ["ecto.drop", "ecto.setup"],
      test: ["ecto.create --quiet", "ecto.migrate --quiet", "test"]
    ]
  end
end
